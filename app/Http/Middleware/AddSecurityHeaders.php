<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        $isHttps = $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https';

        if ($isHttps) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * The marketing site and /admin get the strict baseline. The Kolabing Web App
     * host needs two additions and nothing else — see `webAppScriptSources()`.
     */
    private function contentSecurityPolicy(Request $request): string
    {
        $isWebApp = $this->isWebAppHost($request);

        $scriptSrc = ["'self'", "'unsafe-inline'", 'https://cdn.tailwindcss.com'];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $frameSrc = ["'self'"];
        $connectSrc = ["'self'", 'https:'];

        if ($isWebApp) {
            // Alpine compiles every x-*/@ expression with `new Function(...)`, so the
            // web app cannot run at all without 'unsafe-eval'. This host already
            // allows 'unsafe-inline' scripts, which is the strictly wider hole — an
            // injected <script> never needs eval — so this does not change what an
            // attacker could do here, and it stays off the marketing + admin hosts.
            // Removing it means moving to Alpine's CSP build, which only supports
            // property access and method calls in expressions (no ternaries or
            // comparisons in attributes) and would mean rewriting every webapp view.
            $scriptSrc[] = "'unsafe-eval'";

            // Google Identity Services ("Sign in with Google") loads its client
            // script, injects a stylesheet, and renders the button in an iframe.
            $scriptSrc[] = 'https://accounts.google.com';
            $styleSrc[] = 'https://accounts.google.com';
            $frameSrc[] = 'https://accounts.google.com';

            // Real-time chat dials Reverb over a WebSocket. CSP treats ws:/wss: as
            // their own schemes — `https:` above does NOT cover them — so without
            // this the socket is blocked and chat silently degrades to polling.
            $connectSrc[] = 'wss:';
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            'style-src '.implode(' ', $styleSrc),
            'script-src '.implode(' ', $scriptSrc),
            'frame-src '.implode(' ', $frameSrc),
            'connect-src '.implode(' ', $connectSrc),
            "media-src 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }

    /**
     * Matches the host the web-app routes are registered on (routes/web.php uses
     * the same `config('webapp.host')`), so the two can never drift apart.
     */
    private function isWebAppHost(Request $request): bool
    {
        $host = (string) config('webapp.host');

        return $host !== '' && strcasecmp($request->getHost(), $host) === 0;
    }
}
