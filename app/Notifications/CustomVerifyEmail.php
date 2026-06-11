<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $parts = parse_url($backendUrl);

        preg_match('#/verify-email/([^/]+)/([^/]+)#', $parts['path'] ?? '', $matches);

        return config('app.frontend_url')
            .'/verify-email'
            .'?id='.($matches[1] ?? '')
            .'&hash='.($matches[2] ?? '')
            .(isset($parts['query']) ? '&'.$parts['query'] : '');
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email – Jijel Explorer')
            ->greeting('Welcome to Jijel Explorer, '.$notifiable->name.'!')
            ->line("You're one step away from exploring the hidden gems of Jijel.")
            ->line('Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $url)
            ->line('If you did not create an account, you can safely ignore this email.')
            ->salutation('Best regards, The Jijel Explorer Team');
    }
}
