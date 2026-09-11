<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts off every authenticated request from an account an admin switched off (#254).
 *
 * Sits on the single `auth:sanctum` group in routes/api.php, so one line covers
 * the whole authenticated API rather than ~166 endpoints checking individually.
 *
 * Deactivation already revokes the profile's tokens, so in practice a switched-off
 * account is stopped at authentication. This middleware is what catches the rest:
 * a token issued in the same instant the switch was flipped, a long-lived token
 * that escaped revocation, and any future token type. It answers 403 with a stable
 * `ACCOUNT_DEACTIVATED` code so the app can tell "your session ended" apart from
 * "your account was switched off" and say so.
 */
class EnsureProfileActive
{
    /**
     * The wire code the mobile client matches on. Stable contract — changing it
     * silently turns a clear message back into a generic error.
     */
    public const CODE = 'ACCOUNT_DEACTIVATED';

    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user();

        if ($profile !== null && $profile->is_active === false) {
            // Anything still in the keyring dies here rather than on the next call.
            $profile->tokens()->delete();

            return $this->deactivated();
        }

        return $next($request);
    }

    public static function deactivated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => self::CODE,
            'message' => __('This account has been deactivated. Please contact support.'),
            'errors' => [
                'account' => [__('This account has been deactivated. Please contact support.')],
            ],
        ], 403);
    }
}
