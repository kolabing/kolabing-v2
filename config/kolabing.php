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

];
