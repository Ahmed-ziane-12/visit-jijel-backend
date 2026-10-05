<?php

use App\Enums\PriceUnit;
use App\Models\Business;
use App\Models\BusinessDetail;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->has(Profile::factory()->businessOwner())->create());
});

// ── Creation ───────────────────────────────────────────────────

it('creates a business detail alongside the business', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'hotel',
        'name' => 'Hôtel de la Côte',
        'detail' => [
            'average_price' => 8500,
            'price_unit' => 'night',
            'number_of_rooms' => 42,
            'star_rating' => 3,
            'amenities' => ['Free Wi-Fi', 'Parking'],
            'services' => ['Room service'],
        ],
    ])
        ->assertStatus(201)
        ->assertJsonPath('detail.number_of_rooms', 42)
        ->assertJsonPath('detail.star_rating', 3)
        ->assertJsonPath('detail.price_unit', 'night')
        ->assertJsonPath('detail.amenities', ['Free Wi-Fi', 'Parking']);

    $business = Business::where('name', 'Hôtel de la Côte')->sole();

    expect($business->detail)
        ->not->toBeNull()
        ->and($business->detail->average_price)->toEqual('8500.00')
        ->and($business->detail->price_unit)->toBe(PriceUnit::Night)
        ->and($business->detail->seating_capacity)->toBeNull();
});

it('creates a business without a detail when none is provided', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'restaurant',
        'name' => 'AZ Resto',
    ])->assertStatus(201)
        ->assertJsonPath('detail', null);

    expect(BusinessDetail::count())->toBe(0);
});

it('ignores an entirely blank detail payload', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'touristic_agency',
        'name' => 'AZ Tours',
        'detail' => [
            'average_price' => null,
            'price_unit' => null,
            'amenities' => [],
            'services' => null,
        ],
    ])->assertStatus(201);

    expect(BusinessDetail::count())->toBe(0);
});

// ── Validation ─────────────────────────────────────────────────

it('rejects an unknown price unit', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'hotel',
        'name' => 'Bad Hotel',
        'detail' => ['price_unit' => 'per_fortnight'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['detail.price_unit']);
});

it('rejects a star rating outside one to five', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'hotel',
        'name' => 'Bad Hotel',
        'detail' => ['star_rating' => 9],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['detail.star_rating']);
});

it('rejects negative counts and prices', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'hotel',
        'name' => 'Bad Hotel',
        'detail' => [
            'average_price' => -1,
            'number_of_rooms' => -5,
            'seating_capacity' => -2,
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'detail.average_price',
            'detail.number_of_rooms',
            'detail.seating_capacity',
        ]);
});

it('rejects non string amenities', function () {
    $this->postJson('/api/v1/businesses', [
        'type' => 'hotel',
        'name' => 'Bad Hotel',
        'detail' => ['amenities' => [['nested']]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['detail.amenities.0']);
});

// ── Read ───────────────────────────────────────────────────────

it('exposes the detail on the public business endpoint', function () {
    $business = Business::factory()->create(['type' => 'restaurant']);

    BusinessDetail::factory()->create([
        'business_id' => $business->id,
        'cuisine_type' => 'Algerian',
        'seating_capacity' => 80,
    ]);

    $this->getJson("/api/v1/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('detail.cuisine_type', 'Algerian')
        ->assertJsonPath('detail.seating_capacity', 80);
});

it('returns a null detail for businesses without one', function () {
    $business = Business::factory()->create();

    $this->getJson("/api/v1/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('detail', null);
});

it('deletes the detail when the business is deleted', function () {
    $business = Business::factory()->create();

    BusinessDetail::factory()->create(['business_id' => $business->id]);

    $business->delete();

    expect(BusinessDetail::count())->toBe(0);
});
