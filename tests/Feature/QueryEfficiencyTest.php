<?php

use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Attach a Cloudinary-style media row to a model.
 */
function attachTestMedia(Model $model, string $collection = 'gallery', bool $isCover = true): void
{
    $publicId = 'jijel/tests/'.uniqid('asset-', true);

    $model->media()->create([
        'cloudinary_public_id' => $publicId,
        'url' => "https://res.cloudinary.com/test/image/upload/v1/{$publicId}.jpg",
        'secure_url' => "https://res.cloudinary.com/test/image/upload/v1/{$publicId}.jpg",
        'format' => 'jpg',
        'resource_type' => 'image',
        'collection' => $collection,
        'is_cover' => $isCover,
        'sort_order' => 0,
    ]);
}

/**
 * Count SQL statements that touch the media table while the callback runs.
 */
function mediaQueriesDuring(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();

        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return collect($queries)
        ->filter(fn (array $query) => str_contains($query['query'], 'from "media"'))
        ->count();
}

it('resolves profile avatars from eager-loaded media without extra queries', function () {
    $profiles = Profile::factory()->count(5)->create();

    foreach ($profiles as $profile) {
        attachTestMedia($profile, 'profiles');
    }

    $loaded = Profile::query()
        ->with('media')
        ->whereIn('id', $profiles->pluck('id'))
        ->get();

    $queries = mediaQueriesDuring(fn () => $loaded->each->toArray());

    expect($queries)->toBe(0);
});

it('serializes itinerary items without per-item media queries', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();
    $itinerary = Itinerary::factory()->create(['user_id' => $user->id]);
    $day = $itinerary->days()->create(['day_date' => '2026-11-01', 'day_number' => 1]);

    for ($i = 0; $i < 4; $i++) {
        $destination = Destination::factory()->create();
        attachTestMedia($destination);

        $day->items()->create([
            'destination_id' => $destination->id,
            'title' => "Stop {$i}",
            'item_type' => 'destination',
            'sort_order' => $i,
        ]);
    }

    $loaded = Itinerary::query()
        ->whereKey($itinerary->id)
        ->with([
            'days.items.destination.media',
            'days.items.listing.business',
            'days.items.event.business',
            'days.items.event.destination',
        ])
        ->firstOrFail();

    $queries = mediaQueriesDuring(fn () => $loaded->toArray());

    expect($queries)->toBe(0);
});

it('loads candidate media once per type while generating a trip', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();
    Sanctum::actingAs($user);

    for ($i = 1; $i <= 20; $i++) {
        $destination = Destination::factory()->create([
            'name' => "Jijel spot {$i}",
            'latitude' => 36.82 + ($i % 3) * 0.03,
            'longitude' => 5.77 + intdiv($i, 3) * 0.03,
            'category' => 'beach',
            'state' => 'active',
        ]);

        attachTestMedia($destination);
    }

    $payload = [
        'title' => 'Jijel getaway',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-03',
        'adults' => 2,
        'children' => 0,
        'vibes' => ['beach'],
        'preferences' => ['beaches'],
        'accommodation' => 'notBooked',
        'budget' => [
            'budgetType' => 'standard',
            'customBudget' => null,
            'customBudgetType' => 'daily',
        ],
        'status' => 'draft',
    ];

    $queries = mediaQueriesDuring(function () use ($payload) {
        $this->postJson('/api/v1/trips', $payload)->assertCreated();
    });

    // One query to eager load candidate media and one for the generated
    // itinerary items' destination media. A per-candidate regression would
    // push this well past 20.
    expect($queries)->toBeLessThanOrEqual(4);
});
