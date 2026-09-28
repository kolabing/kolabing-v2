<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
        'from' => env('MAIL_FROM_ADDRESS', 'hello@kolabing.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Kolabing'),
        'message_stream' => env('POSTMARK_MESSAGE_STREAM_ID', 'outbound'),
        // Reply-To on emails that ask the recipient to reply (onboarding drip). The
        // From address can be a no-reply sender; replies must reach a person.
        'reply_to' => env('POSTMARK_REPLY_TO', 'hello@kolabing.com'),
        // Postmark template aliases that exist in a translated copy, listed in full
        // as '<alias>-<locale>' (e.g. 'business-welcome-01-es'). EmailService::send
        // uses the localized alias only when it is listed here, so a translation
        // goes live by publishing it in Postmark and adding it to this list, and an
        // unlisted one can never be requested (Postmark rejects unknown aliases).
        // Comma-separated in POSTMARK_LOCALIZED_ALIASES. Default: none (English).
        'localized_aliases' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('POSTMARK_LOCALIZED_ALIASES', '')),
        ))),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Google OAuth client IDs for different platforms.
    | The mobile app will send ID tokens that need to be verified.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_id_ios' => env('GOOGLE_CLIENT_ID_IOS'),
        'client_id_android' => env('GOOGLE_CLIENT_ID_ANDROID'),
        'client_id_web' => env('GOOGLE_CLIENT_ID_WEB'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apollo.io Website Tracker
    |--------------------------------------------------------------------------
    |
    | Visitor tracker loaded on the marketing site (not /admin, not the web app).
    | An empty APOLLO_APP_ID turns the snippet off.
    |
    */

    'apollo' => [
        'app_id' => env('APOLLO_APP_ID', '6ab2db46aec1e90010bf6d50'),
    ],

    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Sign In Configuration
    |--------------------------------------------------------------------------
    |
    | The Apple client ID is the Bundle ID of your iOS app (e.g. com.kolabing.app).
    | If left empty, audience validation is skipped (useful for development).
    |
    */

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.kolabing.kolabingApp'),
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH', storage_path('app/apple/AuthKey.p8')),
        'iap_environment' => env('APPLE_IAP_ENVIRONMENT', 'sandbox'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
        'base_url' => env('ONESIGNAL_BASE_URL', 'https://api.onesignal.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (web checkout for sales-driven business subscriptions)
    |--------------------------------------------------------------------------
    |
    | Bypasses Apple's IAP fee for subscriptions closed on the web / Android.
    | `allowed_return_hosts` is the https-host allowlist for the checkout
    | success/cancel URLs (the app deep-link scheme `kolabing://` is always
    | allowed); anything else is rejected so the endpoint cannot be abused as an
    | open redirect. Prices are Stripe Price IDs; the EUR amounts are set on
    | those Prices in the Stripe dashboard (see config/subscriptions.php).
    |
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'allowed_return_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('STRIPE_ALLOWED_RETURN_HOSTS', 'kolabing.com,www.kolabing.com,app.kolabing.com')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    |
    | Drives the admin sales-outreach generator (BE-NF-65): Kolab pitch ideas +
    | email copy from the text model, a cover image from the image model. Reached
    | over the plain HTTP client rather than an SDK, so no new composer dependency.
    |
    | Model ids are verified against `GET /v1/models` for this key, not guessed.
    | Missing key => the generator refuses to run and says so, rather than sending
    | a half-composed pitch to a real business.
    |
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-5.2'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        // Image generation is the slow one; a sales pitch is generated
        // interactively, so both need a ceiling well short of a gateway timeout.
        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
        'image_timeout' => (int) env('OPENAI_IMAGE_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram Connect (BE-NF-75, docs/instagram-connect.md)
    |--------------------------------------------------------------------------
    |
    | Instagram API with Instagram Login on Kolabing's own Meta app (permission
    | instagram_business_basic; Business or Creator accounts only). Until Meta
    | approves the permission in App Review only the app's Instagram testers can
    | connect, so the feature ships off: `enabled` opens it to every business and
    | community, `tester_profile_ids` opens it to named Kolabing profiles only.
    | Nothing is reachable without app_id + app_secret.
    |
    */
    'instagram' => [
        'enabled' => (bool) env('INSTAGRAM_ENABLED', false),
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI', 'https://kolabing.com/instagram/callback'),
        'tester_profile_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INSTAGRAM_TESTER_PROFILE_IDS', ''))
        ))),
        'scope' => 'instagram_business_basic',
        'authorize_url' => 'https://www.instagram.com/oauth/authorize',
        'token_url' => 'https://api.instagram.com/oauth/access_token',
        'graph_url' => 'https://graph.instagram.com',
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v25.0'),
        // Where the callback sends the user back: the app deep link, or the web app.
        'app_return_url' => env('INSTAGRAM_APP_RETURN_URL', 'kolabing://instagram/connected'),
        'web_return_url' => env('INSTAGRAM_WEB_RETURN_URL', 'https://app.kolabing.com/settings'),
        'state_ttl_minutes' => (int) env('INSTAGRAM_STATE_TTL_MINUTES', 15),
        // Long-lived tokens last 60 days; the nightly job refreshes them from day 50.
        'refresh_after_days' => (int) env('INSTAGRAM_REFRESH_AFTER_DAYS', 50),
        'media_page_size' => 24,
        'max_import_per_request' => (int) env('INSTAGRAM_MAX_IMPORT_PER_REQUEST', 10),
        // Imported videos: stored mp4 + thumbnail, capped by size (the Instagram
        // API does not return a duration, so length is bounded by the size cap).
        'max_video_mb' => (int) env('INSTAGRAM_MAX_VIDEO_MB', 50),
        'kolab_max_media' => (int) env('INSTAGRAM_KOLAB_MAX_MEDIA', 10),
        'timeout' => (int) env('INSTAGRAM_TIMEOUT', 20),
    ],

];
