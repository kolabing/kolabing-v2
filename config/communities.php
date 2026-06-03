<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Free community cap (NF-6 / NF-7)
    |--------------------------------------------------------------------------
    |
    | A Community Leader may create this many communities for free. Creating
    | one beyond the cap throws CommunityLimitReachedException, which the API
    | surfaces as HTTP 422 with error code "community_limit_reached". This is a
    | NEW gate reserved for the future NF-7 Community Premium upsell. It is NOT
    | the business paywall: it must NEVER call Profile::hasActiveSubscription().
    |
    */
    'max_free_communities' => env('COMMUNITIES_MAX_FREE', 1),

    /*
    |--------------------------------------------------------------------------
    | Default tier
    |--------------------------------------------------------------------------
    |
    | Auto-created when a community is created. New members land here. It is a
    | manual-rule tier (never auto-assigned away by threshold rules; it is the
    | floor every member starts from).
    |
    */
    'default_tier' => [
        'name' => env('COMMUNITIES_DEFAULT_TIER_NAME', 'Member'),
        'rank' => 1,
    ],
];
