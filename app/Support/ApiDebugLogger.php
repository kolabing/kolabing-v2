<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Kolab;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ApiDebugLogger
{
    private const WINDOW_END = '2026-06-04 23:59:59 UTC';

    public static function logValidationFailure(Request $request, array $errors): void
    {
        if (! self::windowIsOpen()) {
            return;
        }

        Log::warning('API validation failure during debug window.', [
            'route' => $request->route()?->uri(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_body' => self::sanitizePayload($request->all()),
            'errors' => $errors,
        ]);
    }

    public static function logKolabPublishFailure(Request $request, Kolab $kolab, Throwable $exception): void
    {
        if (! self::windowIsOpen()) {
            return;
        }

        Log::warning('Kolab publish failure during debug window.', [
            'route' => $request->route()?->uri(),
            'method' => $request->method(),
            'path' => $request->path(),
            'profile_id' => $request->user()?->getAuthIdentifier(),
            'kolab_id' => $kolab->id,
            'kolab_status' => $kolab->status->value,
            'request_body' => self::sanitizePayload($request->all()),
            'error' => $exception->getMessage(),
        ]);
    }

    public static function logTokenIssued(Profile $profile, NewAccessToken $accessToken, NewAccessToken $refreshToken): void
    {
        if (! self::shouldLogTokenLifecycle($profile)) {
            return;
        }

        Log::info('Auth token issued during debug window.', [
            'profile_id' => $profile->id,
            'account_created_at' => $profile->created_at?->toIso8601String(),
            'token_issued_at' => now()->toIso8601String(),
            'access_token_id' => $accessToken->accessToken->id,
            'access_token_expires_at' => $accessToken->accessToken->expires_at?->toIso8601String(),
            'refresh_token_id' => $refreshToken->accessToken->id,
            'refresh_token_expires_at' => $refreshToken->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public static function logRefreshAttempt(string $outcome, ?PersonalAccessToken $refreshToken = null, ?Profile $profile = null): void
    {
        if ($profile !== null && ! self::shouldLogTokenLifecycle($profile)) {
            return;
        }

        if ($profile === null && ! self::windowIsOpen()) {
            return;
        }

        Log::info('Auth refresh attempt during debug window.', [
            'profile_id' => $profile?->id,
            'account_created_at' => $profile?->created_at?->toIso8601String(),
            'refresh_attempted_at' => now()->toIso8601String(),
            'refresh_outcome' => $outcome,
            'refresh_token_id' => $refreshToken?->id,
            'refresh_token_issued_at' => $refreshToken?->created_at?->toIso8601String(),
            'refresh_token_expires_at' => $refreshToken?->expires_at?->toIso8601String(),
        ]);
    }

    public static function logTokenFirstUse(Request $request, PersonalAccessToken $accessToken, Profile $profile): void
    {
        if (! self::shouldLogTokenLifecycle($profile)) {
            return;
        }

        Log::info('Auth token first use during debug window.', [
            'profile_id' => $profile->id,
            'account_created_at' => $profile->created_at?->toIso8601String(),
            'access_token_id' => $accessToken->id,
            'token_issued_at' => $accessToken->created_at?->toIso8601String(),
            'first_use_at' => now()->toIso8601String(),
            'route' => $request->route()?->uri(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }

    private static function shouldLogTokenLifecycle(Profile $profile): bool
    {
        return self::windowIsOpen()
            && $profile->created_at !== null
            && $profile->created_at->greaterThanOrEqualTo(now()->subMinutes(5));
    }

    private static function windowIsOpen(): bool
    {
        return now()->lessThanOrEqualTo(CarbonImmutable::parse(self::WINDOW_END));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        foreach (['password', 'password_confirmation', 'token', 'refresh_token'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
