<?php

use App\Models\User;

test('new clients can register and are authenticated via a session', function () {
    $response = $this->withHeaders(['Origin' => 'http://localhost:3000'])->post('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'role' => 'client',
    ]);

    $response->assertStatus(201)->assertJsonPath('token', null);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

test('business owners are not authenticated until they verify their email', function () {
    $response = $this->withHeaders(['Origin' => 'http://localhost:3000'])->post('/api/v1/register', [
        'name' => 'Test Owner',
        'email' => 'owner@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'role' => 'business_owner',
    ]);

    $response->assertStatus(201)->assertJsonPath('token', null);

    $this->assertGuest();
    $this->assertDatabaseHas('profiles', [
        'user_id' => User::where('email', 'owner@example.com')->first()->id,
        'role' => 'business_owner',
    ]);
});
