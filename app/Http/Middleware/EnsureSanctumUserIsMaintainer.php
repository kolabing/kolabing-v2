<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum-token counterpart to EnsureAdminUserIsMaintainer: gates the token-authenticated
 * admin read API (routes/api.php, prefix admin/*) instead of the session-authenticated
 * Blade admin panel. Runs after auth:sanctum, so $request->user() is whichever tokenable
 * model (User or Profile) owns the presented token.
 */
class EnsureSanctumUserIsMaintainer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isMaintainer()) {
            abort(403);
        }

        return $next($request);
    }
}
