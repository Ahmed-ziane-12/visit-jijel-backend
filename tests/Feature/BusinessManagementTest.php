<?php

use App\Enums\PriceUnit;
use App\Models\Business;
use App\Models\BusinessDetail;
use App\Models\Listing;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function managementOwner(): User
{
    return User::factory()->has(Profile::factory()->businessOwner())->create();
}

function managementClient(): User
{
    return User::factory()->has(Profile::factory()->client())->create();
}

// ── Owner business lookup ─────────────────────────────────────

it('returns a single owned business with its detail', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);
    BusinessDetail::factory()->create(['business_id' => $business->id, 'number_of_rooms' => 12]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/my-businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('id', $business->id)
        ->assertJsonPath('detail.number_of_rooms', 12)
        ->assertJsonStructure(['media', 'detail', 'listings_count']);
});

it('denies reading a business owned by someone else', function () {
    Business::factory()->create();

    Sanctum::actingAs(managementClient());

    $business = Business::first();

    $this->getJson("/api/v1/my-businesses/{$business->id}")
        ->assertStatus(403);
});

// ── Business + detail update ──────────────────────────────────

it('creates the detail on the first update', function () {
    $owner = managementOwner();
    $business = Business::factory()->create([
        'owner_id' => $owner->id,
        'type' => 'restaurant',
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/businesses/{$business->id}", [
        'name' => 'Le Rivage',
        'detail' => [
            'average_price' => 1800,
            'price_unit' => 'person',
            'cuisine_type' => 'Algerian',
            'seating_capacity' => 60,
            'amenities' => ['Terrace'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Le Rivage')
        ->assertJsonPath('detail.cuisine_type', 'Algerian')
        ->assertJsonPath('detail.seating_capacity', 60);

    $business->refresh();

    expect($business->detail)
        ->not->toBeNull()
        ->and($business->detail->average_price)->toEqual('1800.00')
        ->and($business->detail->price_unit)->toBe(PriceUnit::Person)
        ->and($business->detail->number_of_rooms)->toBeNull();
});

it('updates an existing detail in place', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);
    $detail = BusinessDetail::factory()->create([
        'business_id' => $business->id,
        'average_price' => 4000,
        'price_unit' => 'night',
        'number_of_rooms' => 10,
        'star_rating' => 2,
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/businesses/{$business->id}", [
        'detail' => [
            'average_price' => 9500,
            'price_unit' => 'night',
            'number_of_rooms' => 35,
            'star_rating' => 4,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('detail.average_price', '9500.00')
        ->assertJsonPath('detail.number_of_rooms', 35)
        ->assertJsonPath('detail.star_rating', 4);

    expect(BusinessDetail::count())->toBe(1)
        ->and($detail->fresh()->number_of_rooms)->toBe(35);
});

it('resets stale type specific fields when the type changes', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);
    BusinessDetail::factory()->create([
        'business_id' => $business->id,
        'number_of_rooms' => 42,
        'star_rating' => 3,
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/businesses/{$business->id}", [
        'type' => 'restaurant',
        'detail' => [
            'cuisine_type' => 'Seafood',
            'seating_capacity' => 40,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('type', 'restaurant')
        ->assertJsonPath('detail.cuisine_type', 'Seafood')
        ->assertJsonPath('detail.number_of_rooms', null)
        ->assertJsonPath('detail.star_rating', null);
});

it('leaves the detail untouched when the payload omits detail', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);
    BusinessDetail::factory()->create([
        'business_id' => $business->id,
        'number_of_rooms' => 15,
        'star_rating' => 2,
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/businesses/{$business->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('detail.number_of_rooms', 15);

    expect(BusinessDetail::count())->toBe(1);
});

it('rejects invalid detail values on update', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id, 'type' => 'hotel']);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/businesses/{$business->id}", [
        'detail' => [
            'price_unit' => 'per_fortnight',
            'star_rating' => 9,
            'number_of_rooms' => -3,
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'detail.price_unit',
            'detail.star_rating',
            'detail.number_of_rooms',
        ]);
});

// ── Owner listing management ──────────────────────────────────

it('lists every listing for the owner including drafts', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    Listing::factory()->published()->create(['business_id' => $business->id]);
    Listing::factory()->create(['business_id' => $business->id]);
    Listing::factory()->create(['business_id' => $business->id, 'status' => 'archived']);

    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/v1/my-businesses/{$business->id}/listings")
        ->assertOk();

    expect($response->json())->toHaveCount(3);
});

it('denies the owner listing listing to someone else', function () {
    $business = Business::factory()->create();
    Listing::factory()->create(['business_id' => $business->id]);

    Sanctum::actingAs(managementClient());

    $this->getJson("/api/v1/my-businesses/{$business->id}/listings")
        ->assertStatus(403);
});

it('creates, updates and deletes a listing for the owner', function () {
    $owner = managementOwner();
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $created = $this->postJson("/api/v1/businesses/{$business->id}/listings", [
        'title' => 'Weekend escape',
        'description' => 'Two nights with breakfast.',
        'price' => 12000,
        'currency' => 'DZD',
        'capacity' => 2,
        'amenities' => ['Sea view'],
        'status' => 'published',
    ])
        ->assertStatus(201)
        ->assertJsonPath('title', 'Weekend escape');

    $listingId = $created->json('id');

    $this->putJson("/api/v1/businesses/{$business->id}/listings/{$listingId}", [
        'title' => 'Weekend escape — winter',
        'price' => 9000,
    ])
        ->assertOk()
        ->assertJsonPath('title', 'Weekend escape — winter')
        ->assertJsonPath('price', '9000.00');

    $this->deleteJson("/api/v1/businesses/{$business->id}/listings/{$listingId}")
        ->assertOk();

    $this->assertDatabaseMissing('listings', ['id' => $listingId]);
});

it('denies listing management on a business the user does not own', function () {
    $business = Business::factory()->create();

    Sanctum::actingAs(managementOwner());

    $this->postJson("/api/v1/businesses/{$business->id}/listings", ['title' => 'Nope'])
        ->assertStatus(403);
});
