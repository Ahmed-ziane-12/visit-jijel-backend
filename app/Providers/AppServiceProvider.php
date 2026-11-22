<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Services\CloudinaryService;
use Cloudinary\Cloudinary;
use GuzzleHttp\Client;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CloudinaryService::class, function (): CloudinaryService {
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');

            // Resolve a client-less service when the deployment has no
            // Cloudinary credentials. The SDK validates its configuration in
            // the constructor, so building it with empty values would make
            // every media route (even ones that do not touch Cloudinary)
            // throw a 500.
            if (blank($cloudName) || blank($apiKey) || blank($apiSecret)) {
                return new CloudinaryService;
            }

            return new CloudinaryService(new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'signature_algorithm' => config('cloudinary.signature_algorithm'),
                    'signature_version' => 2,
                ],
            ]));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'destination' => Destination::class,
            'business' => Business::class,
            'event' => Event::class,
        ]);

        Mail::extend('brevo', function (array $config): BrevoTransport {
            return new BrevoTransport(
                $config['key'] ?? '',
                new Client(['timeout' => $config['timeout'] ?? 10])
            );
        });

        $this->sanitizeGlobalMailFrom();

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $isAdmin = $notifiable->is_admin ?? false;
            $base = $isAdmin
                ? config('app.admin_url', config('app.frontend_url'))
                : config('app.frontend_url');

            return $base."/reset-password?token=$token&email={$notifiable->getEmailForPasswordReset()}";
        });

        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers();
        });

    }

    /**
     * Enforce a clean global "from" address so hidden control characters in
     * the MAIL_FROM_ADDRESS/MAIL_FROM_NAME env values never break sends.
     */
    protected function sanitizeGlobalMailFrom(): void
    {
        $address = $this->clean((string) config('mail.from.address'));
        $name = $this->clean((string) config('mail.from.name'));

        if ($address !== '') {
            Mail::alwaysFrom($address, $name);
        }
    }

    protected function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
    }
}
