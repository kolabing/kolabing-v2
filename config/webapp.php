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
    | Marketing site
    |--------------------------------------------------------------------------
    | Where a logged-out visitor belongs. Signing out is leaving the product, so
    | it returns people to the public site rather than the app host's own hero.
    | Defaults to APP_URL, which already points at the marketing domain.
    */
    'marketing_url' => env('MARKETING_URL', env('APP_URL', 'https://kolabing.com')),

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

    /*
    |--------------------------------------------------------------------------
    | Real-time chat (Reverb) — browser-facing credentials
    |--------------------------------------------------------------------------
    | The chat page opens a WebSocket to Reverb and falls back to polling when it
    | cannot. These are the four public values a Pusher-protocol client needs;
    | the app *secret* stays server-side (config/reverb.php) and must never be
    | exposed here. These are already populated in production — Laravel Cloud runs
    | the managed Reverb instance (`REVERB_HOST=ws-….laravel.cloud`, TLS on 443),
    | and its allowed-origins list already covers app.kolabing.com. `key` empty
    | disables the socket entirely and the page polls instead, so a fresh
    | environment with no Reverb still has working chat.
    */
    'realtime' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_HOST'),
        // Client-facing port: what the browser dials (443 behind TLS), which is
        // NOT REVERB_SERVER_PORT (what the daemon binds locally).
        'port' => (int) env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
    ],

    /*
    |--------------------------------------------------------------------------
    | App links (one URL, two clients)
    |--------------------------------------------------------------------------
    |
    | A check-in QR encodes `https://app.kolabing.com/checkin/{code}`. With these
    | published, iOS Universal Links and Android App Links hand that URL to the
    | installed app; without them the browser handles it. Same QR either way, which
    | is the point — a printed or on-screen code must not need to know whether the
    | person scanning it has the app.
    |
    | Values belong to the mobile projects: `apple_app_id` is `TEAMID.bundleId`, and
    | the Android fingerprints are the release signing certificate's SHA-256. The
    | routes 404 while these are unset — Apple's CDN caches the association file, so
    | serving a placeholder is worse than serving nothing.
    |
    */
    'app_links' => [
        'apple_app_id' => env('APPLE_APP_ID'),
        'android_package' => env('ANDROID_PACKAGE_NAME', 'com.kolabing.kolabingApp'),
        // Comma-separated, so more than one signing cert (Play App Signing plus a
        // local release key) can be trusted at once.
        'android_sha256' => env('ANDROID_SHA256_FINGERPRINTS'),
        /*
         * The paths worth handing to the app. Everything else stays in the browser:
         * a marketing page opening inside an app is a worse experience, not a better
         * one.
         */
        'paths' => ['/checkin/*', '/c/*'],
    ],

    'deep_link' => env('WEBAPP_DEEP_LINK', 'kolabing://'),

    'app_store_url' => env('KOLABING_IOS_APP_URL'),

    'play_store_url' => env('KOLABING_ANDROID_APP_URL', 'https://play.google.com/store/apps/details?id=com.kolabing.kolabingApp'),
];
