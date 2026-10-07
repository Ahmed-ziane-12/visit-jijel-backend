<?php

use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function tripClientUser(): User
{
    return User::factory()->has(Profile::factory()->client())->create();
}

function tripBusinessOwner(): User
{
    return User::factory()->has(Profile::factory()->businessOwner())->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tripPayload(array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

/**
 * Destinations clustered around Jijel (~3km apart) so they pass the daily geo check.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedNearbyDestinations(int $count, array $overrides = []): void
{
    for ($i = 1; $i <= $count; $i++) {
        Destination::factory()->create(array_merge([
            'name' => "Jijel spot {$i}",
            'latitude' => 36.82 + ($i % 3) * 0.03,
            'longitude' => 5.77 + intdiv($i, 3) * 0.03,
            'category' => 'beach',
            'state' => 'active',
        ], $overrides));
    }
}

it('generates a day-by-day itinerary when the wizard is validated', function () {
    Sanctum::actingAs(tripClientUser());
    seedNearbyDestinations(10);

    $response = $this->postJson('/api/v1/trips', tripPayload());

    $response->assertCreated()
        ->assertJsonPath('title', 'Jijel getaway')
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('preferences.preferences.0', 'beaches');

    expect($response->json('start_date'))->toStartWith('2026-11-01')
        ->and($response->json('end_date'))->toStartWith('2026-11-03');

    expect($response->json('days'))->toHaveCount(3);
    expect($response->json('days.0.day_number'))->toBe(1)
        ->and($response->json('days.2.day_number'))->toBe(3)
        ->and($response->json('days.2.day_date'))->toStartWith('2026-11-03');

    $firstItem = $response->json('days.0.items.0');
    expect($firstItem)->not->toBeNull()
        ->and($firstItem['sort_order'])->toBe(0)
        ->and(substr($firstItem['start_time'], 0, 5))->toBe('08:30')
        ->and(substr($firstItem['end_time'], 0, 5))->toBe('10:30')
        ->and($firstItem['item_type'])->toBe('destination')
        ->and($firstItem['destination_id'])->not->toBeNull()
        ->and($firstItem['latitude'])->toBeFloat()
        ->and($firstItem['longitude'])->toBeFloat();

    $this->assertDatabaseHas('itineraries', [
        'id' => $response->json('id'),
        'user_id' => auth()->id(),
        'status' => 'draft',
    ]);
    $this->assertDatabaseCount('itinerary_days', 3);
});

it('fills no more than four activities per day using the fixed slots', function () {
    Sanctum::actingAs(tripClientUser());
    seedNearbyDestinations(30);

    $response = $this->postJson('/api/v1/trips', tripPayload());

    $response->assertCreated();

    $expectedSlots = ['08:30', '11:00', '13:30', '16:00'];

    foreach ($response->json('days') as $day) {
        expect(count($day['items']))->toBe(4);

        foreach ($day['items'] as $index => $item) {
            expect($item['sort_order'])->toBe($index)
                ->and(substr($item['start_time'], 0, 5))->toBe($expectedSlots[$index]);
        }
    }
});

it('keeps same-day activities geographically coherent', function () {
    Sanctum::actingAs(tripClientUser());
    seedNearbyDestinations(3);

    Destination::factory()->create([
        'name' => 'Remote lagoon',
        'latitude' => 48.85,
        'longitude' => 2.35,
        'category' => 'beach',
        'state' => 'active',
    ]);

    $response = $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
    ]));

    $titles = array_column($response->json('days.0.items'), 'title');

    expect($titles)->toHaveCount(3)
        ->and($titles)->not->toContain('Remote lagoon');
});

it('schedules an event only on the day it takes place', function () {
    Sanctum::actingAs(tripClientUser());
    seedNearbyDestinations(10);

    Event::factory()->published()->create([
        'title' => 'Sea festival',
        'starts_at' => '2026-11-02 10:00:00',
        'ends_at' => '2026-11-02 23:59:00',
        'latitude' => 36.82,
        'longitude' => 5.77,
        'price' => 1000,
    ]);

    $response = $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-02',
    ]));

    $firstDayTypes = array_column($response->json('days.0.items'), 'item_type');
    $secondDayTypes = array_column($response->json('days.1.items'), 'item_type');

    expect($firstDayTypes)->not->toContain('event')
        ->and($secondDayTypes)->toContain('event');

    $eventItem = collect($response->json('days.1.items'))
        ->first(fn (array $item) => $item['item_type'] === 'event');

    expect($eventItem['event_id'])->not->toBeNull()
        ->and($eventItem['destination_id'])->toBeNull();
});

it('schedules published listings as candidates', function () {
    Sanctum::actingAs(tripClientUser());

    $business = Business::factory()->active()->create([
        'latitude' => 36.82,
        'longitude' => 5.77,
        'type' => 'restaurant',
    ]);
    Listing::factory()->published()->create([
        'business_id' => $business->id,
        'title' => 'Harbour table',
        'price' => 4000,
    ]);

    $response = $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
    ]));

    $response->assertCreated();

    $item = collect($response->json('days.0.items'))
        ->firstWhere('item_type', 'listing');

    expect($item)->not->toBeNull()
        ->and($item['listing_id'])->not->toBeNull();
});

it('requires authentication and the client role', function () {
    seedNearbyDestinations(3);

    $this->postJson('/api/v1/trips', tripPayload())
        ->assertUnauthorized();

    Sanctum::actingAs(tripBusinessOwner());

    $this->postJson('/api/v1/trips', tripPayload())
        ->assertForbidden();
});

it('rejects a trip window longer than the thirty day limit', function () {
    Sanctum::actingAs(tripClientUser());

    $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-06',
    ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');

    $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]))->assertCreated();
});

it('returns only unused candidates as recommendations', function () {
    $user = tripClientUser();
    Sanctum::actingAs($user);
    seedNearbyDestinations(8);

    $trip = $this->postJson('/api/v1/trips', tripPayload([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
    ]))->assertCreated();

    $usedIds = collect($trip->json('days.0.items'))
        ->pluck('destination_id')
        ->filter()
        ->all();

    $recommendations = $this->getJson("/api/v1/trips/{$trip->json('id')}/recommendations")
        ->assertOk()
        ->json('data');

    expect($recommendations)->toHaveCount(4);

    $returnedIds = array_column($recommendations, 'id');
    expect(array_intersect($usedIds, $returnedIds))->toBeEmpty();

    foreach ($recommendations as $candidate) {
        expect($candidate['item_type'])->toBe('destination')
            ->and($candidate['id'])->toBeInt()
            ->and($candidate['score'])->toBeNumeric()
            ->and($candidate['score'])->toBeGreaterThan(0);
    }
});

it('denies reading a trip owned by someone else', function () {
    Sanctum::actingAs(tripClientUser());
    seedNearbyDestinations(4);

    $trip = $this->postJson('/api/v1/trips', tripPayload())->assertCreated();

    Sanctum::actingAs(tripClientUser());

    $this->getJson("/api/v1/trips/{$trip->json('id')}")->assertForbidden();
});
