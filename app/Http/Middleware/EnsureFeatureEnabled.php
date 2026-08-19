<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind `config("{feature}.enabled")` — `feature:suggestions`
 * reads `config('suggestions.enabled')`.
 *
 * **404, not 403.** A disabled feature must not advertise itself: 403 tells a
 * caller the endpoint exists and they merely lack access, which is exactly the
 * fact a flag that ships `false` is hiding. An unknown feature name resolves to
 * null and therefore closes the route rather than opening it.
 */
class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ((bool) config("{$feature}.enabled") === true) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('Resource not found'),
                'errors' => [
                    'resource' => [__('The requested resource was not found')],
                ],
            ], 404);
        }

        abort(404);
    }
}
