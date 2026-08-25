<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One URL per page.
 *
 * `www.kolabing.com` was serving the whole site a second time with its own
 * self-referencing canonical, so the two hosts competed as independent documents
 * and split link equity between them. `/pricing/` answered separately from
 * `/pricing` for the same reason. Neither was visible from inside the app: both
 * rendered perfectly.
 *
 * This is belt-and-braces alongside a CDN rule — an edge redirect is cheaper, but
 * this one travels with the code and cannot be lost in a dashboard.
 */
class CanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $redirect = $this->canonicalRedirect($request);

        return $redirect ?? $next($request);
    }

    private function canonicalRedirect(Request $request): ?RedirectResponse
    {
        $host = $request->getHost();
        $path = $request->getPathInfo();

        $targetHost = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        // Trailing slashes, except on the root itself.
        $targetPath = $path !== '/' ? rtrim($path, '/') : $path;
        if ($targetPath === '') {
            $targetPath = '/';
        }

        if ($targetHost === $host && $targetPath === $path) {
            return null;
        }

        $query = $request->getQueryString();

        return redirect(
            $request->getScheme().'://'.$targetHost.$targetPath.($query !== null ? '?'.$query : ''),
            301
        );
    }
}
