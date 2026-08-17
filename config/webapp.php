<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Web App host + links
    |--------------------------------------------------------------------------
    |
    | The Kolabing Web App (register, subscribe, manage Kolabs, feed) is the same
    | Laravel app served on a dedicated hostname so it can call /api/v1 same-origin
    | (no CORS). `host` scopes the web-app routes (routes/web.php); on any other host
    | (e.g. kolabing.com) those routes do not match and the marketing site is served.
    | The store links + deep link drive the "continue in the app" nudge after purchase.
    |
    */

    'host' => env('WEBAPP_HOST', 'app.kolabing.com'),

    /*
    |--------------------------------------------------------------------------
    | Absolute web-app URL (marketing → app funnel)
    |--------------------------------------------------------------------------
    |
    | The marketing site (kolabing.com) is served on a different host, so its
    | CTAs cannot use route() — they need an absolute cross-host URL. Every
    | "log in" / "get started" link on the marketing side is built from this
    | value, e.g. `config('webapp.url').'/register?type=business'` (the register
    | page reads ?type= and skips its role-picker step).
    |
    */

    'url' => env('WEBAPP_URL', 'https://app.kolabing.com'),

    /*
    |--------------------------------------------------------------------------
    | Locales (SEO-friendly, URL-prefixed)
    |--------------------------------------------------------------------------
    | `default` is served at the root ("/login"); the others under a path prefix
    | ("/es/login", "/ca/login") with hreflang alternates. Barcelona-first: es + ca.
    */
    'default_locale' => 'en',
    'locales' => ['en', 'es', 'ca'],
    // Only these appear as a URL prefix (the default has none); used in the route
    // where() constraint and the language switcher.
    'prefixed_locales' => ['es', 'ca'],

    'deep_link' => env('WEBAPP_DEEP_LINK', 'kolabing://'),

    'app_store_url' => env('KOLABING_IOS_APP_URL'),

    'play_store_url' => env('KOLABING_ANDROID_APP_URL', 'https://play.google.com/store/apps/details?id=com.kolabing.kolabingApp'),
];
