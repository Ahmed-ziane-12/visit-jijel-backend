<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        then: function () {
            Route::middleware('api')
                ->prefix('')
                ->group(base_path('routes/admin.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The sanctum stateful middleware enables cookie/session auth (and CSRF
        // protection) only for requests whose Origin/Referer matches the
        // SANCTUM_STATEFUL_DOMAINS list, so the mobile bearer-token flow is
        // unaffected.
        $middleware->prepend(
            EnsureFrontendRequestsAreStateful::class,
        );

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'admin' => EnsureAdmin::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        // Run authorization gates before route model binding so non-admins
        // get 403 instead of a 404 that would leak which IDs exist.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureAdmin::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureSuperAdmin::class,
        );

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
