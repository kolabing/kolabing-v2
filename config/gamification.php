<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Local timezone for mission period buckets
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, but recurring missions (daily / weekly /
    | monthly / seasonal) must roll over at local midnight. This is the single
    | timezone the product operates in; MissionService::periodKeyFor() converts
    | UTC timestamps to it before deriving the period_key, so both the write
    | path (record()) and the read path (GET /me/missions) bucket identically.
    |
    */
    'local_timezone' => env('GAMIFICATION_LOCAL_TIMEZONE', 'Europe/Madrid'),
];
