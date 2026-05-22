<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Profile;
use App\Support\ApiDebugLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LogAuthTokenFirstUse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user instanceof Profile) {
            return $response;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $response;
        }

        $cacheKey = sprintf('auth:first-use:%s', $token->id);

        if (Cache::add($cacheKey, now()->toIso8601String(), now()->addDays(14))) {
            ApiDebugLogger::logTokenFirstUse($request, $token, $user);
        }

        return $response;
    }
}
