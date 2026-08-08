<?php

use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ── Canonical role accessor ───────────────────────────────────

it('derives the canonical role from the users flags and profile', function () {
    $superAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->has(Profile::factory()->businessOwner())->create();
    $client = User::factory()->has(Profile::factory()->client())->create();
    $profileless = User::factory()->create();

    expect($superAdmin->role)->toBe('super_admin');
    expect($admin->role)->toBe('admin');
    expect($owner->role)->toBe('business_owner');
    expect($client->role)->toBe('client');
    expect($profileless->role)->toBe('client');
});

it('serializes the canonical role in api responses', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($admin);

    $this->getJson('/admin/v1/users?all=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $admin->id, 'role' => 'admin']);
});

// ── Admin creating an admin account ───────────────────────────

it('creates real admins from the user store endpoint', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

    $this->postJson('/admin/v1/users', [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'password123',
    ])->assertStatus(201);

    $admin = User::where('email', 'new-admin@example.com')->firstOrFail();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->role)->toBe('admin')
        ->and($admin->profile->role)->toBe('client');
});

// ── Role updates cannot grant admin status ────────────────────

it('rejects admin and super_admin roles on user update', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

    $client = User::factory()->has(Profile::factory()->client())->create();

    $this->putJson("/admin/v1/users/{$client->id}", ['role' => 'admin'])
        ->assertStatus(422);

    $this->putJson("/admin/v1/users/{$client->id}", ['role' => 'super_admin'])
        ->assertStatus(422);

    expect($client->fresh()->profile->role)->toBe('client');
});

it('still updates public roles to business_owner or client', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

    $client = User::factory()->has(Profile::factory()->client())->create();

    $this->putJson("/admin/v1/users/{$client->id}", ['role' => 'business_owner'])
        ->assertOk();

    expect($client->fresh()->profile->role)->toBe('business_owner');
});

// ── Role filter ───────────────────────────────────────────────

it('filters users by canonical role', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

    $superAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->has(Profile::factory()->businessOwner())->create();
    $client = User::factory()->has(Profile::factory()->client())->create();

    $this->getJson('/admin/v1/users?all=1&role=super_admin')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['id' => $superAdmin->id])
        ->assertJsonMissing(['id' => $admin->id]);

    $this->getJson('/admin/v1/users?all=1&role=admin')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $admin->id])
        ->assertJsonMissing(['id' => $superAdmin->id]);

    $this->getJson('/admin/v1/users?all=1&role=business_owner')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $owner->id]);

    $this->getJson('/admin/v1/users?all=1&role=client')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $client->id])
        ->assertJsonMissing(['id' => $owner->id]);
});
