<?php

use App\Models\Business;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Models\Itinerary;
use App\Models\Listing;
use App\Models\Profile;
use App\Models\Review;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function ownerUser(): User
{
    return User::factory()->has(Profile::factory()->businessOwner())->create();
}

function clientUser(): User
{
    return User::factory()->has(Profile::factory()->client())->create();
}

function actingAsUser(User $user): User
{
    Sanctum::actingAs($user);

    return $user;
}

// ── Business ──────────────────────────────────────────────────

it('restricts business creation to business owners', function () {
    $payload = ['type' => 'hotel', 'name' => 'Test Hotel'];

    actingAsUser(clientUser());
    $this->postJson('/api/v1/businesses', $payload)
        ->assertStatus(403);

    actingAsUser(ownerUser());
    $this->postJson('/api/v1/businesses', $payload)
        ->assertStatus(201);
});

it('allows a business owner to update their own business', function () {
    $owner = actingAsUser(ownerUser());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $this->putJson("/api/v1/businesses/{$business->id}", ['name' => 'Renamed'])
        ->assertStatus(200)
        ->assertJson(['name' => 'Renamed']);
});

it('denies other users from updating a business', function () {
    $owner = ownerUser();
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    actingAsUser(clientUser());
    $this->putJson("/api/v1/businesses/{$business->id}", ['name' => 'Renamed'])
        ->assertStatus(403);
});

it('allows a business owner to delete their own business', function () {
    $owner = actingAsUser(ownerUser());
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    $this->deleteJson("/api/v1/businesses/{$business->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
});

it('denies non-owners from deleting a business', function () {
    $owner = ownerUser();
    $business = Business::factory()->create(['owner_id' => $owner->id]);

    actingAsUser(clientUser());
    $this->deleteJson("/api/v1/businesses/{$business->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('businesses', ['id' => $business->id]);
});

// ── Listing ───────────────────────────────────────────────────

it('allows a listing owner to update their listing', function () {
    $owner = actingAsUser(ownerUser());
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $listing = Listing::factory()->create(['business_id' => $business->id]);

    $this->putJson("/api/v1/businesses/{$business->id}/listings/{$listing->id}", ['title' => 'Renamed listing'])
        ->assertStatus(200)
        ->assertJson(['title' => 'Renamed listing']);
});

it('denies other users from updating a listing', function () {
    $owner = ownerUser();
    $business = Business::factory()->create(['owner_id' => $owner->id]);
    $listing = Listing::factory()->create(['business_id' => $business->id]);

    actingAsUser(clientUser());
    $this->putJson("/api/v1/businesses/{$business->id}/listings/{$listing->id}", ['title' => 'Renamed listing'])
        ->assertStatus(403);
});

// ── Itinerary ─────────────────────────────────────────────────

it('allows an itinerary owner to view and update it', function () {
    $owner = actingAsUser(clientUser());
    $itinerary = Itinerary::factory()->create(['user_id' => $owner->id]);

    $this->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertStatus(200);

    $this->putJson("/api/v1/itineraries/{$itinerary->id}", ['title' => 'New title'])
        ->assertStatus(200);
});

it('denies other users from viewing or updating an itinerary', function () {
    $owner = clientUser();
    $itinerary = Itinerary::factory()->create(['user_id' => $owner->id]);

    actingAsUser(clientUser());
    $this->getJson("/api/v1/itineraries/{$itinerary->id}")
        ->assertStatus(403);

    $this->putJson("/api/v1/itineraries/{$itinerary->id}", ['title' => 'New title'])
        ->assertStatus(403);
});

// ── Calendar event ────────────────────────────────────────────

it('allows a calendar event owner to update it', function () {
    $owner = actingAsUser(clientUser());
    $calendarEvent = CalendarEvent::factory()->create(['user_id' => $owner->id]);

    $this->putJson("/api/v1/calendar-events/{$calendarEvent->id}", ['title' => 'New title'])
        ->assertStatus(200)
        ->assertJson(['title' => 'New title']);
});

it('denies other users from updating a calendar event', function () {
    $owner = clientUser();
    $calendarEvent = CalendarEvent::factory()->create(['user_id' => $owner->id]);

    actingAsUser(clientUser());
    $this->putJson("/api/v1/calendar-events/{$calendarEvent->id}", ['title' => 'New title'])
        ->assertStatus(403);
});

// ── Review ────────────────────────────────────────────────────

it('allows a review author to delete their review', function () {
    $author = actingAsUser(clientUser());
    $review = Review::factory()->create(['user_id' => $author->id]);

    $this->deleteJson("/api/v1/reviews/{$review->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
});

it('denies other users from deleting a review', function () {
    $author = clientUser();
    $review = Review::factory()->create(['user_id' => $author->id]);

    actingAsUser(clientUser());
    $this->deleteJson("/api/v1/reviews/{$review->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('reviews', ['id' => $review->id]);
});

// ── Event ─────────────────────────────────────────────────────

it('restricts event creation to business owners', function () {
    $payload = [
        'title' => 'Beach Festival',
        'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeek()->addHours(3)->format('Y-m-d H:i:s'),
    ];

    actingAsUser(clientUser());
    $this->postJson('/api/v1/events', $payload)
        ->assertStatus(403);

    actingAsUser(ownerUser());
    $this->postJson('/api/v1/events', $payload)
        ->assertStatus(201);
});

it('allows an event creator to update their event', function () {
    $creator = actingAsUser(ownerUser());
    $event = Event::factory()->create(['created_by' => $creator->id]);

    $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Renamed event'])
        ->assertStatus(200)
        ->assertJson(['title' => 'Renamed event']);
});

it('denies other users from updating an event', function () {
    $creator = ownerUser();
    $event = Event::factory()->create(['created_by' => $creator->id]);

    actingAsUser(clientUser());
    $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Renamed event'])
        ->assertStatus(403);
});
