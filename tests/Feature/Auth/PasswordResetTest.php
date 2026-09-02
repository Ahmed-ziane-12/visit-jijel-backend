<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/api/v1/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('mail routing strips control characters from a dirty stored email', function () {
    $user = User::factory()->create();

    $dirty = "az.ahmedziane@example.com\x1F";

    $user->setRawAttributes(['email' => $dirty] + $user->getAttributes(), true);

    expect($user->routeNotificationFor('mail'))->toBe('az.ahmedziane@example.com');
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/api/v1/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function (object $notification) use ($user) {
        $response = $this->post('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);

        return true;
    });
});
