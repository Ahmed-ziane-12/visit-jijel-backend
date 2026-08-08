<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('blocks unauthenticated requests from admin routes', function () {
    $this->getJson('/admin/v1/users')
        ->assertStatus(401);
});

it('blocks non-admin users from admin routes', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/admin/v1/users')
        ->assertStatus(403);

    $this->getJson('/admin/v1/businesses')
        ->assertStatus(403);

    $this->deleteJson('/admin/v1/destinations/1')
        ->assertStatus(403);
});

it('allows admins to access platform management routes', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $this->getJson('/admin/v1/users')
        ->assertStatus(200);

    $this->getJson('/admin/v1/businesses')
        ->assertStatus(200);

    $this->getJson('/admin/v1/destinations')
        ->assertStatus(200);

    $this->getJson('/admin/v1/stats')
        ->assertStatus(200);
});

it('blocks regular admins from super admin routes', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));

    $this->getJson('/admin/v1/activity-logs')
        ->assertStatus(403);

    $this->getJson('/admin/v1/admins')
        ->assertStatus(403);
});

it('allows super admins to access super admin routes', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

    $this->getJson('/admin/v1/activity-logs')
        ->assertStatus(200);

    $this->getJson('/admin/v1/admins')
        ->assertStatus(200);
});

it('allows authenticated users to access public api routes', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/businesses')
        ->assertStatus(200);
});
