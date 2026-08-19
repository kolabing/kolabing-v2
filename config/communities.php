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
    | Invite / join link base URL
    |--------------------------------------------------------------------------
    |
    | Canonical shareable join link for a community is "<base>/<slug>", e.g.
    | https://kolabing.com/c/run-club-ab12cd. invite_only communities may also
    | hand out a pre-authorizing token; the token link is "<base>/<slug>?invite=<token>".
    | Override with COMMUNITIES_INVITE_BASE_URL in non-prod environments.
    |
    */
    'invite_base_url' => env('COMMUNITIES_INVITE_BASE_URL', 'https://kolabing.com/c'),

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

    /*
    |--------------------------------------------------------------------------
    | Invitation lifetime
    |--------------------------------------------------------------------------
    |
    | How long a pending community_invitations row stays claimable. A leader can
    | always resend, which refreshes the window.
    |
    */
    'invitation_ttl_days' => env('COMMUNITIES_INVITATION_TTL_DAYS', 30),
];
