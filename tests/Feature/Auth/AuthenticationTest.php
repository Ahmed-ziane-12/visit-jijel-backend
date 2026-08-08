<?php

use App\Models\Profile;
use App\Models\User;

function authStatefulHeaders(): array
{
    return ['Origin' => 'http://localhost:3000'];
}

test('users can authenticate using the login screen', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    $response = $this->withHeaders(authStatefulHeaders())->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertOk()->assertJson(['role' => 'client']);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    $this->withHeaders(authStatefulHeaders())->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(401);

    $this->assertGuest();
});

test('business owners must verify their email before logging in', function () {
    $user = User::factory()->unverified()->has(Profile::factory()->businessOwner())->create();

    $this->withHeaders(authStatefulHeaders())->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(403)->assertJson(['email_unverified' => true]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    $this->actingAs($user);

    $this->withHeaders(authStatefulHeaders())->post('/api/v1/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);

    // auth:sanctum switches the default guard to "sanctum" within a test,
    // so assert against the web session guard directly.
    $this->assertGuest('web');
});
