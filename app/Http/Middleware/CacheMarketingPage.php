<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Let the CDN hold marketing HTML for a few minutes.
 *
 * Laravel's default `no-cache, private` meant Cloudflare answered every marketing
 * request with `cf-cache-status: BYPASS` — each crawl and each visitor paid for a
 * full server render of a page that changes a few times a month. `s-maxage` is
 * shared-cache only, so the CDN caches while browsers keep revalidating, and
 * `stale-while-revalidate` means a visitor never waits for the refresh.
 *
 * Applied only to public marketing GETs. Anything personalised (the app host, the
 * admin, anything behind auth) must keep the private default, so this middleware
 * refuses to touch a response that carries a session cookie or is not a plain 200.
 */
class CacheMarketingPage
{
    /** Seconds the CDN may serve a cached copy. */
    private const SHARED_MAX_AGE = 300;

    /** Seconds it may keep serving the stale copy while it refetches. */
    private const STALE_WHILE_REVALIDATE = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        // A Set-Cookie means something user-specific happened; caching it shared
        // would leak one visitor's response to the next.
        if ($response->headers->getCookies() !== []) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            sprintf(
                'public, max-age=0, s-maxage=%d, stale-while-revalidate=%d',
                self::SHARED_MAX_AGE,
                self::STALE_WHILE_REVALIDATE
            )
        );

        return $response;
    }
}
