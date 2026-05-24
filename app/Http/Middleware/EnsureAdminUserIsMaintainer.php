<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUserIsMaintainer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if (! $user instanceof User || ! $user->isMaintainer()) {
            abort(403);
        }

        return $next($request);
    }
}
