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

    // Cross-vertical entries shown on a city hub page.
    'hub_limit' => 12,

    // CTA targets (the two live Cal.com discovery calls + the marketing pages).
    'community_cta_url' => env('KOLABING_BOOK_A_CALL_URL_COMMUNITY', 'https://cal.com/kolabing/community-discovery'),
    'business_cta_url' => env('KOLABING_BOOK_A_CALL_URL_BUSINESS', 'https://cal.com/kolabing/business-discovery'),

];
