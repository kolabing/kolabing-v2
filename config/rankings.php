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
    'author_name' => env('RANKINGS_AUTHOR_NAME', 'Maria Perez'),
    'author_title' => env('RANKINGS_AUTHOR_TITLE', 'founder & CMO'),
    'author_url' => env('RANKINGS_AUTHOR_URL', null),

    // Per-city human verifier named in the methodology (checkable-facts review).
    'reviewer_name' => env('RANKINGS_REVIEWER_NAME', 'our editorial team'),

    // Directory map (Leaflet + CARTO Voyager tiles — no API key). City centres and,
    // where curated, neighbourhood centres so the hub map can drill in per city.
    'map' => [
        'cities' => [
            'Barcelona' => [41.3874, 2.1686],
            'Madrid' => [40.4168, -3.7038],
            'Berlin' => [52.5200, 13.4050],
            'Paris' => [48.8566, 2.3522],
            'Lisbon' => [38.7223, -9.1393],
            'Amsterdam' => [52.3676, 4.9041],
            'Tallinn' => [59.4370, 24.7536],
            'Warsaw' => [52.2297, 21.0122],
        ],
        'neighbourhoods' => [
            'Barcelona' => [
                'Gràcia' => [41.4036, 2.1520],
                'Poblenou' => [41.4045, 2.1996],
                'El Born' => [41.3849, 2.1817],
                'Raval' => [41.3799, 2.1686],
                'Eixample' => [41.3903, 2.1610],
                'Barceloneta' => [41.3797, 2.1893],
                'Sant Martí' => [41.4184, 2.2010],
                'Gothic' => [41.3833, 2.1777],
                'Sant Antoni' => [41.3790, 2.1585],
                'Sants' => [41.3750, 2.1330],
            ],
        ],
    ],

    // Max curated entries shown on a city hub page (>= the largest hub so the full
    // curated list renders; topic-only communities are already excluded from the hub).
    'hub_limit' => 20,

    // CTA targets (the two live Cal.com discovery calls + the marketing pages).
    'community_cta_url' => env('KOLABING_BOOK_A_CALL_URL_COMMUNITY', 'https://cal.com/kolabing/community-discovery'),
    'business_cta_url' => env('KOLABING_BOOK_A_CALL_URL_BUSINESS', 'https://cal.com/kolabing/business-discovery'),

];
