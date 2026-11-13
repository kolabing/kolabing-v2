<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sending to unclaimed profiles (SERR-1344)
    |--------------------------------------------------------------------------
    |
    | An unclaimed profile is one the Kolabing team created before its owner
    | joined. Sending it an offer (a message with a claim link) stays OFF until
    | Daniel turns it on. With the switch off the team can still create and
    | prepare unclaimed profiles and drafts; `send()` refuses them.
    |
    */
    'allow_unclaimed_send' => (bool) env('KOLABING_TEAM_ALLOW_UNCLAIMED_SEND', false),

    // An offer is open for this many days after it is sent (or resent).
    'offer_expiry_days' => 7,
];
