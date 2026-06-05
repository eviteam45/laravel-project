<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token) {
            $base = rtrim(config('app.frontend_url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());
            $url = "{$base}/reset-password?token={$token}&email={$email}";

            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Reset your SolarIncentives password')
                ->greeting('Hello '.$notifiable->name.'!')
                ->line('We received a request to reset the password for your SolarIncentives account.')
                ->action('Reset Password', $url)
                ->line("This password reset link will expire in {$expire} minutes.")
                ->line('If you did not request a password reset, no further action is required — your password stays the same.')
                ->salutation('Regards, The SolarIncentives Team');
        });
    }
}
