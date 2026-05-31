<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchProfileActivity
{
    /**
     * Cooldown between writes for a given profile, in seconds.
     */
    private const COOLDOWN_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $profile = $request->user();

        if ($profile instanceof Profile) {
            $now = now();
            $last = $profile->last_active_at;

            if ($last === null || $last->lt($now->copy()->subSeconds(self::COOLDOWN_SECONDS))) {
                Profile::query()
                    ->whereKey($profile->getKey())
                    ->update(['last_active_at' => $now]);
            }
        }

        return $response;
    }
}
