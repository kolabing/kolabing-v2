<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404s a route whose target profile has been switched off (#258).
 *
 * Without this the profile-detail routes answer 200 with a hollow body: the
 * `profiles` row still resolves through route-model binding — deliberately, so
 * that Sanctum and the admin panel keep working — while the sub-profile that
 * carries the name and photo is scoped away. The caller gets a page for someone
 * who is supposed to be gone, with the identity blanked out. A 404 is the honest
 * answer, and it is the same answer a stranger's id has always given.
 *
 * Bound to the API's `profiles/{profile}` routes only. The admin panel resolves
 * the same model and must keep seeing switched-off accounts.
 */
class EnsureTargetProfileActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $target = $request->route('profile');

        if ($target instanceof Profile && $target->is_active === false) {
            abort(404);
        }

        return $next($request);
    }
}
