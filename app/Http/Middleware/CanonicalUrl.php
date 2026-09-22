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
 *
 * The redirect scheme is hardcoded to `https` rather than read from
 * `$request->getScheme()`. This middleware is `prepend()`-ed onto the global stack
 * (bootstrap/app.php), which runs it BEFORE Laravel's own TrustProxies middleware —
 * so at this point in the request, `getScheme()` still sees the raw, plain-HTTP hop
 * from Laravel Cloud's internal load balancer to the app container, not the
 * original client's HTTPS request. Confirmed live 2026-09-14: a GET to
 * `https://www.kolabing.com/...` came back as a 301 to `http://kolabing.com/...`
 * — the downgrade breaks Secure-flagged session/CSRF cookies on whatever request
 * follows, which is why `/admin/login` intermittently failed for anyone entering
 * the site via `www` (their POST landed on the wrong scheme's cookie jar). The app
 * is HTTPS-only in prod (Laravel Cloud's edge is the only public entry point, see
 * bootstrap/app.php's TrustProxies comment), so hardcoding is both correct and
 * simpler than fixing the middleware ordering.
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
            'https://'.$targetHost.$targetPath.($query !== null ? '?'.$query : ''),
            301
        );
    }
}
