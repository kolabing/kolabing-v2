<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------
    | Partner status thresholds
    |--------------------------------------------------------------------
    |
    | Minimums a business must meet to reach each tier. Evaluated from
    | highest tier down; the first tier whose thresholds are all met wins.
    | Cancellation rate is intentionally not a criterion yet: the schema
    | has no column attributing a cancellation to the business vs. the
    | community side, so it can't be scored fairly (see the gamification
    | audit, Part 9, open decision on cancellation attribution).
    |
    */
    'tiers' => [
        'community_favourite' => [
            'min_completed_kolabs' => 8,
            'min_average_rating' => 4.5,
            'min_repeat_partners' => 2,
        ],
        'trusted_partner' => [
            'min_completed_kolabs' => 3,
            'min_reviews' => 3,
            'min_average_rating' => 4.0,
        ],
        'active_partner' => [
            'min_completed_kolabs' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Discovery visibility boost
    |--------------------------------------------------------------------
    |
    | Additive points added to a business-authored Kolab's discovery match
    | score for a community viewer, based on the business's partner status.
    | Kept separate from the fit-relevance signals in
    | DiscoveryOpportunityService::MATCH_SIGNALS so "why this ranked here"
    | stays legible: fit vs. trust are different concepts.
    |
    */
    'visibility_boost_points' => [
        'trusted_partner' => 5,
        'community_favourite' => 10,
    ],

    /*
    |--------------------------------------------------------------------
    | Reminder cadences (hours after the anchor event)
    |--------------------------------------------------------------------
    */
    'review_reminder_cadence_hours' => [0, 60, 168],
    'second_offer_prompt_cadence_hours' => [72, 168, 336],

    /*
    |--------------------------------------------------------------------
    | Reactivation
    |--------------------------------------------------------------------
    */
    'reactivation_inactivity_days' => 14,
    'reactivation_resend_after_days' => 14,

    /*
    |--------------------------------------------------------------------
    | Monthly goal
    |--------------------------------------------------------------------
    |
    | A rolling calendar-month collaboration goal, shown as progress toward
    | a target — deliberately NOT a streak. There is no "broken streak"
    | state: a quiet month resets cleanly to 0/goal next month rather than
    | penalising the business, since collaboration cadence for cafes,
    | studios, and venues is naturally seasonal/lumpy (see the gamification
    | audit, Part 4G).
    |
    */
    'monthly_goal_count' => 1,

];
