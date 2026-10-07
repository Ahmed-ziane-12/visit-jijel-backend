<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\Event;
use App\Models\Itinerary;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Seven-phase itinerary generation:
 *  1. loadCandidates  â€” destinations, listings and events matching the trip window
 *  2. scoreCandidates â€” preference, rating, budget, popularity and bonus scoring
 *  3. sortCandidates  â€” descending by score
 *  4. constructDays   â€” one day per calendar date of the trip
 *  5. fillTimeSlots   â€” up to 4 activities per day (08:30 â†’ 18:00, 2h + 30min gaps)
 *  6. filterByProximity â€” deduplication and per-day geographic coherence (haversine)
 *  7. persist         â€” transactional write to itineraries / itinerary_days / itinerary_items
 */
class ItineraryGenerator
{
    public const MAX_ACTIVITIES_PER_DAY = 4;

    public const MAX_TRIP_DAYS = 30;

    public const MAX_CANDIDATES_PER_TYPE = 200;

    /** Straight-line spread allowed between two activities of the same day (km). */
    public const MAX_DAY_SPREAD_KM = 80.0;

    /** Travel time between two stops â€” hardcoded for now, it matches the slot gap. */
    public const TRAVEL_TIME_MINUTES = 30;

    public const ACTIVITY_MINUTES = 120;

    public const SLOT_GAP_MINUTES = 30;

    /**
     * Fixed daily slots: morning (2) and afternoon (2).
     *
     * @var array<int, array{start: string, end: string}>
     */
    public const SLOTS = [
        ['start' => '08:30', 'end' => '10:30'],
        ['start' => '11:00', 'end' => '13:00'],
        ['start' => '13:30', 'end' => '15:30'],
        ['start' => '16:00', 'end' => '18:00'],
    ];

    private const WEIGHT_INTEREST = 40;

    private const WEIGHT_RATING = 18;

    private const WEIGHT_BUDGET = 18;

    private const WEIGHT_POPULARITY = 12;

    private const WEIGHT_BONUS = 12;

    private const DAILY_BUDGET_DEFAULTS = [
        'budget' => 4000,
        'standard' => 10000,
        'luxury' => 25000,
    ];

    /** Quiz answer â†’ canonical candidate keywords. */
    private const INTEREST_KEYWORDS = [
        'beaches' => ['beach'],
        'beach' => ['beach'],
        'mountain' => ['nature'],
        'nature' => ['nature'],
        'food' => ['food'],
        'history' => ['historical', 'cultural'],
        'adventure' => ['sport'],
        'city' => ['urban'],
    ];

    /** Business type â†’ canonical candidate keyword. */
    private const BUSINESS_TYPE_KEYWORDS = [
        'restaurant' => 'food',
        'touristic_agency' => 'sport',
        'hotel' => 'urban',
        'real_estate_agency' => 'urban',
    ];

    // â”€â”€ Public API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param  array<string, mixed>  $input  validated StoreTripRequest payload
     */
    public function generate(User $user, array $input): Itinerary
    {
        $start = CarbonImmutable::parse($input['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($input['end_date'])->startOfDay();
        $interests = $this->interests($input);
        $dailyBudget = $this->dailyBudget($input, $start, $end);

        $candidates = $this->loadCandidates($start, $end);                       // phase 1
        $this->scoreCandidates($candidates, $interests, $dailyBudget);           // phase 2
        $this->sortCandidates($candidates);                                      // phase 3
        $days = $this->constructDays($start, $end);                              // phase 4
        $schedule = $this->fillTimeSlots($days, $candidates, $start, $end);      // phase 5 + 6

        return $this->persist($user, $input, $schedule);                         // phase 7
    }

    /**
     * Top candidates not used by the given itinerary â€” feeds the "add activity" panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommend(Itinerary $itinerary, int $limit = 12): array
    {
        $preferences = $itinerary->preferences ?? [];
        $start = $itinerary->start_date
            ? $itinerary->start_date->toImmutable()->startOfDay()
            : CarbonImmutable::today();
        $end = $itinerary->end_date
            ? $itinerary->end_date->toImmutable()->startOfDay()
            : $start;

        $candidates = $this->loadCandidates($start, $end);
        $this->scoreCandidates($candidates, $this->interests($preferences), $this->dailyBudget($preferences, $start, $end));
        $this->sortCandidates($candidates);

        return array_map(
            fn (ItineraryCandidate $candidate) => $candidate->toArray(),
            $this->unusedCandidates($itinerary, $candidates, $limit)
        );
    }

    // â”€â”€ Phase 1 â€” candidate loading â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @return array<int, ItineraryCandidate>
     */
    private function loadCandidates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $destinations = Destination::query()
            ->where('state', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES_PER_TYPE)
            ->get();

        $listings = Listing::query()
            ->published()
            ->whereHas('business', function ($query) {
                $query->where('is_active', true)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude');
            })
            ->with(['business:id,name,type,latitude,longitude,is_active', 'business.detail:id,business_id,star_rating'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES_PER_TYPE)
            ->get();

        $events = Event::query()
            ->published()
            ->where('starts_at', '<=', $end->endOfDay()->toDateTimeString())
            ->where('ends_at', '>=', $start->startOfDay()->toDateTimeString())
            ->with(['destination:id,latitude,longitude,category', 'business:id,type,latitude,longitude'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES_PER_TYPE)
            ->get();

        $candidates = [];

        foreach ($destinations as $destination) {
            $keywords = array_merge([$destination->category], $destination->tags ?? []);
            $candidates[] = new ItineraryCandidate(
                type: 'destination',
                id: $destination->id,
                title: $destination->name,
                description: $destination->description,
                latitude: (float) $destination->latitude,
                longitude: (float) $destination->longitude,
                rating: (float) ($destination->approved_reviews_avg_rating ?? 0),
                reviewCount: (int) ($destination->approved_reviews_count ?? 0),
                featured: (bool) $destination->is_featured,
                keywords: $this->normalizeKeywords($keywords),
                category: $destination->category,
                image: $destination->cover_image_url,
            );
        }

        foreach ($listings as $listing) {
            $business = $listing->business;
            $businessKeyword = self::BUSINESS_TYPE_KEYWORDS[$business?->type] ?? null;
            $candidates[] = new ItineraryCandidate(
                type: 'listing',
                id: $listing->id,
                title: $listing->title,
                description: $listing->description,
                latitude: (float) $business->latitude,
                longitude: (float) $business->longitude,
                price: $listing->price !== null ? (float) $listing->price : null,
                rating: (float) ($listing->approved_reviews_avg_rating ?? $business?->detail?->star_rating ?? 0),
                reviewCount: (int) ($listing->approved_reviews_count ?? 0),
                keywords: array_filter([$businessKeyword]),
                category: $businessKeyword,
                image: $listing->cover_image_url,
            );
        }

        foreach ($events as $event) {
            $latitude = $event->latitude ?? $event->destination?->latitude ?? $event->business?->latitude ?? null;
            $longitude = $event->longitude ?? $event->destination?->longitude ?? $event->business?->longitude ?? null;

            if ($latitude === null || $longitude === null) {
                continue;
            }

            $category = $event->destination?->category
                ?? self::BUSINESS_TYPE_KEYWORDS[$event->business?->type] ?? null;

            $candidates[] = new ItineraryCandidate(
                type: 'event',
                id: $event->id,
                title: $event->title,
                description: $event->description,
                latitude: (float) $latitude,
                longitude: (float) $longitude,
                price: $event->price !== null ? (float) $event->price : null,
                rating: (float) ($event->approved_reviews_avg_rating ?? 0),
                reviewCount: (int) ($event->approved_reviews_count ?? 0),
                keywords: array_filter([$category]),
                category: $category,
                eventDate: $event->starts_at->toDateString(),
                eventEndDate: $event->ends_at->toDateString(),
                image: $event->cover_image_url,
            );
        }

        return $candidates;
    }

    // â”€â”€ Phase 2 â€” scoring â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param  array<int, ItineraryCandidate>  $candidates
     * @param  array<int, string>  $interests
     */
    private function scoreCandidates(array &$candidates, array $interests, float $dailyBudget): void
    {
        foreach ($candidates as $candidate) {
            $score = $this->interestScore($candidate, $interests)
                + $this->ratingScore($candidate)
                + $this->budgetScore($candidate, $dailyBudget)
                + $this->popularityScore($candidate)
                + $this->bonusScore($candidate);

            $candidate->score = min(100.0, max(0.0, $score));
        }
    }

    /**
     * @param  array<int, string>  $interests
     */
    private function interestScore(ItineraryCandidate $candidate, array $interests): float
    {
        if ($interests === []) {
            return self::WEIGHT_INTEREST / 2;
        }

        if ($candidate->category !== null && in_array($candidate->category, $interests, true)) {
            return (float) self::WEIGHT_INTEREST;
        }

        if (array_intersect($interests, $candidate->keywords) !== []) {
            return self::WEIGHT_INTEREST * 0.75;
        }

        if ($candidate->keywords === []) {
            return self::WEIGHT_INTEREST / 2;
        }

        return 0.0;
    }

    private function ratingScore(ItineraryCandidate $candidate): float
    {
        if ($candidate->rating <= 0) {
            return self::WEIGHT_RATING / 2;
        }

        return min(1.0, $candidate->rating / 5) * self::WEIGHT_RATING;
    }

    private function budgetScore(ItineraryCandidate $candidate, float $dailyBudget): float
    {
        if ($candidate->price === null) {
            return self::WEIGHT_BUDGET / 2;
        }

        if ($dailyBudget <= 0) {
            return self::WEIGHT_BUDGET;
        }

        return match (true) {
            $candidate->price <= $dailyBudget => (float) self::WEIGHT_BUDGET,
            $candidate->price <= $dailyBudget * 1.5 => self::WEIGHT_BUDGET * 0.5,
            default => 0.0,
        };
    }

    private function popularityScore(ItineraryCandidate $candidate): float
    {
        return min(1.0, $candidate->reviewCount / 10) * self::WEIGHT_POPULARITY;
    }

    private function bonusScore(ItineraryCandidate $candidate): float
    {
        $bonus = ($candidate->featured ? self::WEIGHT_BONUS * 0.5 : 0)
            + ($candidate->eventDate !== null ? self::WEIGHT_BONUS : 0);

        return min((float) self::WEIGHT_BONUS, $bonus);
    }

    // â”€â”€ Phase 3 â€” sorting â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param  array<int, ItineraryCandidate>  $candidates
     */
    private function sortCandidates(array &$candidates): void
    {
        usort(
            $candidates,
            fn (ItineraryCandidate $left, ItineraryCandidate $right) => $right->score <=> $left->score
        );
    }

    // â”€â”€ Phase 4 â€” day construction â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @return array<int, CarbonImmutable>
     */
    private function constructDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    // â”€â”€ Phase 5 + 6 â€” time-slot filling, dedup, geo proximity â”€â”€

    /**
     * @param  array<int, CarbonImmutable>  $days
     * @param  array<int, ItineraryCandidate>  $candidates
     * @return array<int, array{date: CarbonImmutable, items: array<int, array{candidate: ItineraryCandidate, slot: int}>}>
     */
    private function fillTimeSlots(array $days, array $candidates, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $schedule = [];
        $usedKeys = [];
        $usedTitles = [];

        foreach ($days as $day) {
            $placed = [];
            $items = [];

            for ($slot = 0; $slot < self::MAX_ACTIVITIES_PER_DAY; $slot++) {
                foreach ($candidates as $candidate) {
                    if (isset($usedKeys[$candidate->key()])) {
                        continue;
                    }

                    if (isset($usedTitles[$this->normalizedTitle($candidate->title)])) {
                        continue;
                    }

                    if (! $this->eligibleOnDate($candidate, $day)) {
                        continue;
                    }

                    if (! $this->geographicallyCoherent($candidate, $placed)) {
                        continue;
                    }

                    $items[] = ['candidate' => $candidate, 'slot' => $slot];
                    $placed[] = $candidate;
                    $usedKeys[$candidate->key()] = true;
                    $usedTitles[$this->normalizedTitle($candidate->title)] = true;

                    break;
                }
            }

            $schedule[] = ['date' => $day, 'items' => $items];
        }

        return $schedule;
    }

    private function eligibleOnDate(ItineraryCandidate $candidate, CarbonImmutable $day): bool
    {
        if ($candidate->eventDate === null) {
            return true;
        }

        $date = $day->toDateString();

        return $date >= $candidate->eventDate && $date <= ($candidate->eventEndDate ?? $candidate->eventDate);
    }

    /**
     * @param  array<int, ItineraryCandidate>  $placed
     */
    private function geographicallyCoherent(ItineraryCandidate $candidate, array $placed): bool
    {
        foreach ($placed as $other) {
            if ($this->distanceKm($candidate, $other) > self::MAX_DAY_SPREAD_KM) {
                return false;
            }
        }

        return true;
    }

    private function distanceKm(ItineraryCandidate $left, ItineraryCandidate $right): float
    {
        $earthRadiusKm = 6371.0;
        $deltaLat = deg2rad($right->latitude - $left->latitude);
        $deltaLng = deg2rad($right->longitude - $left->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($left->latitude))
            * cos(deg2rad($right->latitude))
            * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // â”€â”€ Phase 7 â€” persistence â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, array{date: CarbonImmutable, items: array<int, array{candidate: ItineraryCandidate, slot: int}>}>  $schedule
     */
    private function persist(User $user, array $input, array $schedule): Itinerary
    {
        return DB::transaction(function () use ($user, $input, $schedule) {
            $itinerary = $user->itineraries()->create([
                'title' => $input['title'] ?? 'My Trip',
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'status' => $input['status'] ?? 'draft',
                'visibility' => 'private',
                'preferences' => $input,
            ]);

            foreach (array_values($schedule) as $index => $day) {
                $itineraryDay = $itinerary->days()->create([
                    'day_date' => $day['date']->toDateString(),
                    'day_number' => $index + 1,
                ]);

                foreach ($day['items'] as $entry) {
                    $candidate = $entry['candidate'];
                    $slot = self::SLOTS[$entry['slot']];

                    $itineraryDay->items()->create([
                        'destination_id' => $candidate->type === 'destination' ? $candidate->id : null,
                        'listing_id' => $candidate->type === 'listing' ? $candidate->id : null,
                        'event_id' => $candidate->type === 'event' ? $candidate->id : null,
                        'title' => $candidate->title,
                        'notes' => $candidate->description,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'sort_order' => $entry['slot'],
                        'item_type' => $candidate->type,
                    ]);
                }
            }

            return $itinerary->load([
                'days.items.destination',
                'days.items.listing.business',
                'days.items.event.business',
                'days.items.event.destination',
            ]);
        });
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function interests(array $input): array
    {
        $interests = [];
        $answers = array_merge($input['vibes'] ?? [], $input['preferences'] ?? []);

        foreach ($answers as $answer) {
            foreach (self::INTEREST_KEYWORDS[$answer] ?? [] as $keyword) {
                $interests[$keyword] = $keyword;
            }
        }

        return array_values($interests);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function dailyBudget(array $input, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $budget = $input['budget'] ?? [];
        $type = $budget['budgetType'] ?? 'standard';

        if ($type === 'custom') {
            $amount = (float) ($budget['customBudget'] ?? 0);

            if (($budget['customBudgetType'] ?? 'daily') === 'overall') {
                $days = max(1, $start->diffInDays($end) + 1);

                return $days > 0 ? $amount / $days : $amount;
            }

            return $amount;
        }

        return (float) (self::DAILY_BUDGET_DEFAULTS[$type] ?? self::DAILY_BUDGET_DEFAULTS['standard']);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeKeywords(array $values): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : null,
            $values
        )));
    }

    private function normalizedTitle(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $title)));
    }

    /**
     * Candidates not yet scheduled in the itinerary, highest score first.
     *
     * @param  array<int, ItineraryCandidate>  $candidates
     * @return array<int, ItineraryCandidate>
     */
    private function unusedCandidates(Itinerary $itinerary, array $candidates, int $limit): array
    {
        $usedKeys = [];
        $usedTitles = [];

        foreach ($itinerary->items as $item) {
            foreach (['destination', 'listing', 'event'] as $type) {
                $id = $item->{$type.'_id'};

                if ($id !== null) {
                    $usedKeys["{$type}:{$id}"] = true;
                }
            }

            $usedTitles[$this->normalizedTitle($item->title)] = true;
        }

        $unused = [];

        foreach ($candidates as $candidate) {
            if (isset($usedKeys[$candidate->key()]) || isset($usedTitles[$this->normalizedTitle($candidate->title)])) {
                continue;
            }

            $unused[] = $candidate;

            if (count($unused) >= $limit) {
                break;
            }
        }

        return $unused;
    }
}
