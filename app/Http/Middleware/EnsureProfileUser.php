<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app API only serves app accounts (`Profile`).
 *
 * Sanctum accepts any tokenable model, and the maintainer read token minted by
 * `IssueMaintainerApiToken` belongs to a `User`. Without this guard such a token
 * reached controllers and services type-hinted to Profile (ProfileService,
 * KolabService, DiscoveryOpportunityService, ...) and every call died with a
 * TypeError, answered as 500. It now gets a stable 403 `PROFILE_REQUIRED`.
 * The token-scoped admin API (routes/api.php, prefix admin/*) is a separate
 * group and is unaffected.
 */
class EnsureProfileUser
{
    /**
     * Stable wire code for "authenticated, but not an app account".
     */
    public const CODE = 'PROFILE_REQUIRED';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user instanceof Profile) {
            return response()->json([
                'success' => false,
                'code' => self::CODE,
                'message' => __('This endpoint requires an app account.'),
                'errors' => [
                    'account' => [__('This endpoint requires an app account.')],
                ],
            ], 403);
        }

        return $next($request);
    }
}
