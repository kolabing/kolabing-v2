<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('POSTHOG_ENABLED', false),
    'api_key' => env('POSTHOG_API_KEY'),
    'project_api_key' => env('POSTHOG_PROJECT_API_KEY'),
    'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
    'timeout' => (int) env('POSTHOG_TIMEOUT', 5),
];
