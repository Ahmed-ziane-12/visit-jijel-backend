<?php

use App\Providers\AppServiceProvider;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

it('strips control characters from email values before building addresses', function () {
    $provider = new AppServiceProvider(app());

    $cleaner = (new ReflectionClass($provider))->getMethod('clean');

    expect($cleaner->invoke($provider, "noreply@example.com\x1F"))
        ->toBe('noreply@example.com')
        ->and($cleaner->invoke($provider, "wrapped\n"))->toBe('wrapped');
});

it('sends successfully when the configured source address is dirty', function () {
    config(['mail.default' => 'log']);

    config(['mail.from.address' => "noreply@example.com\x1F", 'mail.from.name' => 'Support']);

    $provider = new AppServiceProvider(app());
    $sanitizer = (new ReflectionClass($provider))->getMethod('sanitizeGlobalMailFrom');
    $sanitizer->invoke($provider);

    Mail::to('to@example.com')->send(
        (new Mailable)->subject('test')->html('<p>hi</p>')
    );

    expect(true)->toBeTrue();
});
