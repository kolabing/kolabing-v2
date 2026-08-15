<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale for the Kolabing Web App from the `{locale}` route prefix
 * (/es, /ca); the un-prefixed routes fall back to the default locale (en).
 */
class SetWebappLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $locales = (array) config('webapp.locales', ['en']);

        app()->setLocale(
            is_string($locale) && in_array($locale, $locales, true)
                ? $locale
                : config('webapp.default_locale', 'en')
        );

        return $next($request);
    }
}
