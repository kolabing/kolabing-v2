<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Book-a-call URLs (Cal.com discovery calls)
    |--------------------------------------------------------------------------
    |
    | External scheduling links for the landing-page "Book a call" CTAs, wired to
    | the Kolabing Cal.com account. There are two role-specific 20-min discovery
    | calls; `book_a_call_url` is the generic fallback (defaults to the community
    | call). Override any of them via the environment.
    |
    */

    'book_a_call_url' => env('KOLABING_BOOK_A_CALL_URL', 'https://cal.com/kolabing/community-discovery'),

    'book_a_call_url_community' => env('KOLABING_BOOK_A_CALL_URL_COMMUNITY', 'https://cal.com/kolabing/community-discovery'),

    'book_a_call_url_business' => env('KOLABING_BOOK_A_CALL_URL_BUSINESS', 'https://cal.com/kolabing/business-discovery'),

    /*
    |--------------------------------------------------------------------------
    | Public Kolab pages (kolabing.com/kolabs)
    |--------------------------------------------------------------------------
    |
    | The open-web view of the marketplace. `min_description_length` is a
    | presentability floor, not a quality filter — see
    | PublicKolabFeedService::publishable() for what it can and cannot do.
    |
    | `indexable` controls whether these pages invite search engines and appear in
    | sitemap.xml. It ships false on purpose: production still holds test listings
    | that would be indexed as the product's shop window (BE-FX-20). Flip it once
    | the data is curated — nothing else needs to change.
    |
    */

    'public_kolabs' => [
        'min_description_length' => (int) env('KOLABING_PUBLIC_KOLAB_MIN_DESCRIPTION', 20),
        'indexable' => (bool) env('KOLABING_PUBLIC_KOLABS_INDEXABLE', false),
    ],

];
