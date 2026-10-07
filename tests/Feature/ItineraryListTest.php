<?php

use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists only the owned itineraries with their days and items', function () {
    $client = User::factory()->has(Profile::factory()->client())->create();
    $other = User::factory()->has(Profile::factory()->client())->create();

    $trip = Itinerary::factory()->create([
        'user_id' => $client->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-02',
    ]);
    Itinerary::factory()->create(['user_id' => $other->id]);

    $destination = Destination::factory()->create();
    $day = $trip->days()->create(['day_date' => '2026-11-01', 'day_number' => 1]);
    $day->items()->create([
        'destination_id' => $destination->id,
        'title' => 'Corniche',
        'start_time' => '08:30',
        'end_time' => '10:30',
        'sort_order' => 0,
        'item_type' => 'destination',
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson('/api/v1/itineraries');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', $trip->title)
        ->assertJsonCount(1, 'data.0.days')
        ->assertJsonPath('data.0.days.0.day_number', 1)
        ->assertJsonCount(1, 'data.0.days.0.items')
        ->assertJsonPath('data.0.days.0.items.0.title', 'Corniche')
        ->assertJsonMissingPath('data.1');
});
