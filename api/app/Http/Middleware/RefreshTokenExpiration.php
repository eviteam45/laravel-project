<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slides a personal-access-token's expiry forward on activity so an actively-used
 * session doesn't get logged out at the fixed TTL. To avoid a write on every
 * request, it only extends once the token is past the halfway point of its life.
 */
class RefreshTokenExpiration
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $minutes = (int) config('sanctum.expiration');
        $token = $request->user()?->currentAccessToken();

        if ($minutes > 0 && $token instanceof PersonalAccessToken && $token->exists) {
            $halfLife = now()->addMinutes((int) ($minutes / 2));

            if (! $token->expires_at || $token->expires_at->lt($halfLife)) {
                $token->forceFill(['expires_at' => now()->addMinutes($minutes)])->save();
            }
        }

        return $response;
    }
}
