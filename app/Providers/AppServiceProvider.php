<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use App\Services\CloudinaryService;
use Cloudinary\Cloudinary;
use GuzzleHttp\Client;
use Illuminate\Auth\Notifications\ResetPassword;
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
            return new CloudinaryService(new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
            ]));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', function (array $config): BrevoTransport {
            return new BrevoTransport(
                $config['key'] ?? '',
                new Client(['timeout' => $config['timeout'] ?? 10])
            );
        });

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
}
