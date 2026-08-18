<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Community rankings (public lead-magnet directory)
    |--------------------------------------------------------------------------
    |
    | The public "best {topic} in {city}" pages. The ranked list is a LIVE
    | projection of crm_accounts (type=community, listed=true) — re-rank / edit /
    | add / remove all happen in /admin/crm. This config holds only the display
    | knobs: the named editor (E-E-A-T), the CTA links, and how many entries a hub
    | shows.
    |
    */

    'editor_name' => env('RANKINGS_EDITOR_NAME', 'The Kolabing editorial team'),

    // Accountable human byline + author entity (E-E-A-T / GEO citation). Rendered as
    // "Ranked and reviewed by {author}, {author_title}" and emitted as schema.org Person
    // with sameAs, so answer engines have a real author to attribute the list to.
    'author_name' => env('RANKINGS_AUTHOR_NAME', 'Daniel Martinez'),
    'author_title' => env('RANKINGS_AUTHOR_TITLE', 'founder of Kolabing'),
    'author_url' => env('RANKINGS_AUTHOR_URL', 'https://www.linkedin.com/in/daniel-martinez-serra/'),

    // Per-city human verifier named in the methodology (checkable-facts review).
    'reviewer_name' => env('RANKINGS_REVIEWER_NAME', 'Maria'),

    // Max curated entries shown on a city hub page (>= the largest hub so the full
    // curated list renders; topic-only communities are already excluded from the hub).
    'hub_limit' => 20,

    // CTA targets (the two live Cal.com discovery calls + the marketing pages).
    'community_cta_url' => env('KOLABING_BOOK_A_CALL_URL_COMMUNITY', 'https://cal.com/kolabing/community-discovery'),
    'business_cta_url' => env('KOLABING_BOOK_A_CALL_URL_BUSINESS', 'https://cal.com/kolabing/business-discovery'),

];
