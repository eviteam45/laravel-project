<?php

namespace App\Providers;

use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Stable polymorphic aliases. Persisted in *_type columns instead of FQCNs
     * so class renames/moves never orphan audit history, documents, or notes.
     * enforceMorphMap() also flips on requireMorphMap(), so EVERY polymorphic
     * model must appear here or it will throw at runtime.
     */
    public const MORPH_MAP = [
        'user' => User::class,
        'contractor' => Contractor::class,
        'customer' => Customer::class,
        'project' => Project::class,
        'application' => IncentiveApplication::class,
        'document' => Document::class,
        'note' => Note::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);

        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        if ($this->app->isProduction() && empty(env('FRONTEND_URL'))) {
            Log::warning('FRONTEND_URL is not set in production; CORS and password-reset links default to http://localhost:3000.');
        }

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
