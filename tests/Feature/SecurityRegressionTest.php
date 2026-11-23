<?php

use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Itinerary;
use App\Models\ItineraryDay;
use App\Models\Listing;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Security regressions from the backend audit
|--------------------------------------------------------------------------
|
| Every test in this file guards a leak or privilege escalation that used to
| be possible. Keep them: they are the safety net for future refactors.
|
*/

function secUser(): User
{
    return User::factory()->create();
}

function secAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
}

function secSuperAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
}

function secDay(Itinerary $itinerary, int $number = 1): ItineraryDay
{
    return $itinerary->days()->create([
        'day_date' => $itinerary->start_date->copy()->addDays($number - 1),
        'day_number' => $number,
    ]);
}

// ── Nested resources ──────────────────────────────────────────

it('cannot reach a day through an itinerary it does not belong to', function () {
    $owner = secUser();
    $stranger = secUser();

    $ownItinerary = Itinerary::factory()->for($owner)->create();
    $foreignItinerary = Itinerary::factory()->for($stranger)->create();
    $foreignDay = secDay($foreignItinerary);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$foreignDay->id}")
        ->assertNotFound();

    $this->putJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$foreignDay->id}", [
        'notes' => 'hijacked',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$foreignDay->id}")
        ->assertNotFound();

    expect($foreignDay->fresh())->not->toBeNull()
        ->and($foreignDay->fresh()->notes)->not->toBe('hijacked');
});

it('cannot reach an item through a day it does not belong to', function () {
    $owner = secUser();
    $stranger = secUser();

    $ownItinerary = Itinerary::factory()->for($owner)->create();
    $ownDay = secDay($ownItinerary);

    $foreignDay = secDay(Itinerary::factory()->for($stranger)->create());
    $foreignItem = $foreignDay->items()->create([
        'title' => 'Secret stop',
        'item_type' => 'custom',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$ownDay->id}/items/{$foreignItem->id}")
        ->assertNotFound();

    $this->putJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$ownDay->id}/items/{$foreignItem->id}", [
        'title' => 'hijacked',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/itineraries/{$ownItinerary->id}/days/{$ownDay->id}/items/{$foreignItem->id}")
        ->assertNotFound();

    expect($foreignItem->fresh()->title)->toBe('Secret stop');
});

// ── Calendar-event ownership ──────────────────────────────────

it('rejects attaching a calendar event to someone elses itinerary', function () {
    $client = User::factory()->has(Profile::factory()->client())->create();
    $foreignItinerary = Itinerary::factory()->for(secUser())->create();

    Sanctum::actingAs($client);

    $this->postJson('/api/v1/calendar-events', [
        'itinerary_id' => $foreignItinerary->id,
        'title' => 'Sneaky event',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
        'source' => 'manual',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('itinerary_id');

    $this->assertDatabaseMissing('calendar_events', [
        'itinerary_id' => $foreignItinerary->id,
    ]);
});

it('accepts a calendar event linked to the users own itinerary', function () {
    $client = User::factory()->has(Profile::factory()->client())->create();
    $itinerary = Itinerary::factory()->for($client)->create();

    Sanctum::actingAs($client);

    $this->postJson('/api/v1/calendar-events', [
        'itinerary_id' => $itinerary->id,
        'title' => 'My event',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
        'source' => 'manual',
    ])->assertStatus(201);

    $this->assertDatabaseHas('calendar_events', [
        'user_id' => $client->id,
        'itinerary_id' => $itinerary->id,
    ]);
});

// ── Public visibility ─────────────────────────────────────────

it('hides unverified businesses from the public api', function () {
    $unverified = Business::factory()->active()->create();
    $inactive = Business::factory()->verified()->create();

    $this->getJson("/api/v1/businesses/{$unverified->id}")->assertNotFound();
    $this->getJson("/api/v1/businesses/{$inactive->id}")->assertNotFound();

    $this->getJson('/api/v1/businesses')
        ->assertOk()
        ->assertJsonMissing(['id' => $unverified->id])
        ->assertJsonMissing(['id' => $inactive->id]);
});

it('hides draft listings from anonymous visitors', function () {
    $business = Business::factory()->active()->verified()->create();
    $draft = Listing::factory()->for($business)->create();
    $published = Listing::factory()->for($business)->published()->create();

    $this->getJson("/api/v1/businesses/{$business->id}/listings/{$draft->id}")
        ->assertNotFound();

    $this->getJson("/api/v1/businesses/{$business->id}/listings/{$published->id}")
        ->assertOk()
        ->assertJsonPath('id', $published->id);

    $this->getJson("/api/v1/businesses/{$business->id}/listings")
        ->assertOk()
        ->assertJsonMissing(['id' => $draft->id])
        ->assertJsonFragment(['id' => $published->id]);
});

it('hides draft events from anonymous visitors', function () {
    $draft = Event::factory()->create();
    $published = Event::factory()->published()->create();

    $this->getJson("/api/v1/events/{$draft->id}")->assertNotFound();
    $this->getJson("/api/v1/events/{$published->id}")->assertOk();

    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonMissing(['id' => $draft->id])
        ->assertJsonFragment(['id' => $published->id]);
});

it('hides non-active destinations from anonymous visitors', function () {
    $active = Destination::factory()->create(['state' => 'active']);
    $hidden = Destination::factory()->create(['state' => 'inactive']);

    $this->getJson("/api/v1/destinations/{$hidden->id}")->assertNotFound();
    $this->getJson("/api/v1/destinations/{$active->id}")->assertOk();

    $this->getJson('/api/v1/destinations')
        ->assertOk()
        ->assertJsonMissing(['id' => $hidden->id])
        ->assertJsonFragment(['id' => $active->id]);
});

// ── Public profile privacy ────────────────────────────────────

it('does not expose private account fields on a public profile', function () {
    $user = User::factory()->has(Profile::factory()->client())->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('id', $user->id);

    expect($response->json('email'))->toBeNull()
        ->and($response->json('is_admin'))->toBeNull()
        ->and($response->json('is_super_admin'))->toBeNull()
        ->and($response->json('must_reset_password'))->toBeNull()
        ->and($response->json('created_by'))->toBeNull()
        ->and($response->json('email_verified_at'))->toBeNull();
});

it('shows the email to the account owner only', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

// ── Admin privilege guards ────────────────────────────────────

it('blocks regular admins from the super admin user mutations', function () {
    Sanctum::actingAs(secAdmin());

    $target = secUser();

    $this->postJson('/admin/v1/users', [
        'name' => 'Backdoor Admin',
        'email' => 'backdoor@example.com',
        'password' => 'password123',
    ])->assertForbidden();

    $this->deleteJson("/admin/v1/users/{$target->id}")->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'backdoor@example.com']);
    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('stops admins from taking over another admin or a super admin', function () {
    $actor = secAdmin();
    $otherAdmin = secAdmin();
    $superAdmin = secSuperAdmin();

    Sanctum::actingAs($actor);

    $this->putJson("/admin/v1/users/{$otherAdmin->id}", [
        'email' => 'attacker@example.com',
    ])->assertForbidden();

    $this->putJson("/admin/v1/users/{$superAdmin->id}", [
        'email' => 'attacker@example.com',
    ])->assertForbidden();

    expect($otherAdmin->fresh()->email)->not->toBe('attacker@example.com')
        ->and($superAdmin->fresh()->email)->not->toBe('attacker@example.com');
});

it('forbids deleting yourself or a super admin', function () {
    $superAdmin = secSuperAdmin();
    $otherSuperAdmin = secSuperAdmin();

    Sanctum::actingAs($superAdmin);

    $this->deleteJson("/admin/v1/users/{$superAdmin->id}")
        ->assertStatus(422);

    $this->deleteJson("/admin/v1/users/{$otherSuperAdmin->id}")
        ->assertStatus(422);

    $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    $this->assertDatabaseHas('users', ['id' => $otherSuperAdmin->id]);
});

it('enforces the five admin account cap', function () {
    Sanctum::actingAs(secSuperAdmin());

    User::factory()->count(5)->create([
        'is_admin' => true,
        'is_super_admin' => false,
    ]);

    $this->postJson('/admin/v1/users', [
        'name' => 'Sixth Admin',
        'email' => 'sixth@example.com',
        'password' => 'password123',
    ])->assertStatus(422);

    $this->assertDatabaseMissing('users', ['email' => 'sixth@example.com']);
});

it('does not leak author account fields in public post feeds', function () {
    $author = User::factory()->has(Profile::factory()->client())->create([
        'email' => 'author@example.com',
    ]);

    Post::create([
        'user_id' => $author->id,
        'body' => 'Hello Jijel',
    ]);

    $response = $this->getJson("/api/v1/users/{$author->id}/posts")->assertOk();

    expect($response->json('data.0.user.email'))->toBeNull()
        ->and($response->json('data.0.user.is_admin'))->toBeNull()
        ->and($response->json('data.0.user.is_super_admin'))->toBeNull()
        ->and($response->json('data.0.user.must_reset_password'))->toBeNull()
        ->and($response->json('data.0.user.name'))->toBe($author->name);
});

it('never mass assigns privilege flags', function () {
    $user = User::create([
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.com',
        'password' => 'password123',
        'is_admin' => true,
        'is_super_admin' => true,
        'must_reset_password' => false,
    ]);

    // The attributes were dropped by the mass-assignment guard; the database
    // defaults take over on the next read.
    $user->refresh();

    expect($user->is_admin)->toBeFalse()
        ->and($user->is_super_admin)->toBeFalse();
});
