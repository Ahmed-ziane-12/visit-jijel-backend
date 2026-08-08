<?php

use App\Models\Profile;
use App\Models\User;

function sessionHeaders(): array
{
    return ['Origin' => 'http://localhost:3000'];
}

// ── CSRF ──────────────────────────────────────────────────────

test('sanctum csrf-cookie issues an XSRF-TOKEN cookie for stateful clients', function () {
    $this->withHeaders(sessionHeaders())
        ->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

// NOTE: Laravel skips CSRF verification when running under PHPUnit
// (PreventRequestForgery::runningUnitTests()), so token rejection (HTTP 419)
// is verified against a live `php artisan serve` instance in the smoke test.

test('stateful requests with a valid X-XSRF-TOKEN header are accepted', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    $xsrfToken = $this->withHeaders(sessionHeaders())
        ->get('/sanctum/csrf-cookie')
        ->getCookie('XSRF-TOKEN', false);

    $this->withHeaders([
        ...sessionHeaders(),
        'X-XSRF-TOKEN' => $xsrfToken,
    ])->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->assertJson(['role' => 'client', 'token' => null]);

    $this->assertAuthenticated();
});

test('bearer-token (mobile) requests bypass CSRF and receive a token', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    // No Origin header → non-stateful → no CSRF required and a token is issued.
    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    expect($response->json('token'))->not->toBeNull();

    $this->withToken($response->json('token'))->getJson('/api/user')->assertOk();
});

// ── Rate limiting (Phase 1 fix) ───────────────────────────────

test('login is rate limited to five attempts per minute', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

// ── Edge cases (Phase 1 fixes) ────────────────────────────────

test('users without a profile can still log in', function () {
    $user = User::factory()->create();

    $this->withHeaders(sessionHeaders())->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->assertJsonPath('role', null);

    $this->assertAuthenticated();
});

// ── Full session smoke flow ───────────────────────────────────

test('full session flow: csrf-cookie, login, protected call, logout', function () {
    $user = User::factory()->has(Profile::factory()->client())->create();

    // 1. csrf-cookie
    $csrf = $this->withHeaders(sessionHeaders())->get('/sanctum/csrf-cookie');
    $csrf->assertNoContent()->assertCookie('XSRF-TOKEN');

    $sessionIdBeforeLogin = session()->getId();

    // 2. login with the XSRF token → 200, session established
    $login = $this->withHeaders([
        ...sessionHeaders(),
        'X-XSRF-TOKEN' => $csrf->getCookie('XSRF-TOKEN', false),
    ])->post('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $login->assertOk()->assertJson(['role' => 'client', 'token' => null]);
    $this->assertAuthenticated();

    // 2b. session ID is regenerated on login (session-fixation protection)
    expect(session()->getId())->not->toBe($sessionIdBeforeLogin);

    // 3. protected call using the session cookie
    $this->withHeaders(sessionHeaders())->getJson('/api/user')->assertOk();

    // 4. logout (session regenerate refreshes the XSRF token)
    $this->withHeaders([
        ...sessionHeaders(),
        'X-XSRF-TOKEN' => $login->getCookie('XSRF-TOKEN', false),
    ])->post('/api/v1/logout')->assertOk();

    $this->assertGuest('web');
});
