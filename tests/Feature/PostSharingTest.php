<?php

use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('shares a destination as a post', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();
    $destination = Destination::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'body' => 'Worth visiting!',
        'shareable_type' => 'destination',
        'shareable_id' => $destination->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('body', 'Worth visiting!')
        ->assertJsonPath('shareable_type', 'destination')
        ->assertJsonPath('shareable.id', $destination->id)
        ->assertJsonPath('shareable.name', $destination->name)
        ->assertJsonPath('user_id', $user->id);
});

it('shares a business as a post', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();
    $business = Business::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'shareable_type' => 'business',
        'shareable_id' => $business->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('shareable_type', 'business')
        ->assertJsonPath('shareable.id', $business->id);
});

it('shares an event as a post', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();
    $event = Event::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/posts', [
        'shareable_type' => 'event',
        'shareable_id' => $event->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('shareable_type', 'event')
        ->assertJsonPath('shareable.id', $event->id);
});

it('rejects a share when the shared item does not exist', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/posts', [
        'shareable_type' => 'destination',
        'shareable_id' => 999999,
    ])->assertUnprocessable();
});
