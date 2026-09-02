<?php

use App\Providers\AppServiceProvider;

it('strips control characters from email values before building addresses', function () {
    $provider = new AppServiceProvider(app());

    $cleaner = (new ReflectionClass($provider))->getMethod('clean');

    expect($cleaner->invoke($provider, "noreply@example.com\x1F"))
        ->toBe('noreply@example.com')
        ->and($cleaner->invoke($provider, "wrapped\n"))->toBe('wrapped');
});
