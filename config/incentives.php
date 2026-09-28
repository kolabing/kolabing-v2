<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Incentives v1 (Daniel, 28 Sep 2026)
|--------------------------------------------------------------------------
| A = city league (monthly season per city), C = organiser levels (the
| reward ladder). Only in-app, verified actions count (kolab completed in
| the app, QR check-in at the door, review in the app). Nobody is ranked by
| money. Perk copy lives here, not in code.
*/

return [

    'organiser_levels' => [

        // Trusted = at least this many kolabs completed in the app in a
        // calendar month (Europe/Madrid) with at least `min_checkins`
        // verified check-ins at each. Meeting it in a month keeps the
        // organiser Trusted through the following month; a month without it
        // drops them back to Rising.
        'trusted' => [
            'kolabs_per_month' => 1,
            'min_checkins' => (int) env('INCENTIVES_TRUSTED_MIN_CHECKINS', 20),
        ],

        // Top = Trusted AND inside the top of their city league division:
        // the top `city_league.top_ranks`, or `city_league.top_percent` when
        // the division is large. Live table this month or last month's final.

        // `perks` is the full perk set a level holds (next_perks = what the
        // next level adds).
        'levels' => [
            'new' => [
                'label' => 'New',
                'perks' => [],
            ],
            'rising' => [
                'label' => 'Rising',
                'perks' => [
                    'Badge on your applications',
                ],
            ],
            'trusted' => [
                'label' => 'Trusted',
                'perks' => [
                    'Badge on your applications',
                    'Listed and shown to venues',
                    'Ranked higher where venues find communities',
                ],
            ],
            'top' => [
                'label' => 'Top',
                'perks' => [
                    'Top organiser badge',
                    'Shown first to venues',
                    'Personal introductions to sports brands, fashion brands and venues',
                ],
                // Raises "Intro due" on the admin user page until the team
                // marks the introduction done.
                'admin_intro_flag' => true,
            ],
        ],

        'criteria_labels' => [
            'kolabs_completed' => 'Kolabs completed in the app',
            'monthly_kolab' => 'Kolab this month with 20+ check-ins',
            'kolab_checkins' => 'Best kolab turnout this month',
            'league_rank' => 'City league rank',
        ],

        // Short perk used in next_reward copy per target level.
        'reward_copy' => [
            'rising' => 'badge on your applications',
            'trusted' => 'listed and shown to venues',
            'top' => 'personal intros to sports brands, fashion brands and venues',
        ],

        // Venue-side ranking (business viewers: community kolabs in Explore
        // and the community list). discovery_score = level boost + league
        // factor + last season's division-winner boost, stored nightly.
        'discovery' => [
            'enabled' => (bool) env('INCENTIVES_DISCOVERY_BOOST', true),
            'level_points' => [
                'trusted' => 5,
                'top' => 8,
            ],
            // points × weight, with last month's points decayed; capped.
            'league_weight' => 0.02,
            'league_previous_month_decay' => 0.5,
            'league_cap' => 10,
            // New/Rising organisers are shown lower, not hidden, unless this is on.
            'hide_below_trusted' => (bool) env('INCENTIVES_HIDE_BELOW_TRUSTED', false),
        ],
    ],

    'city_league' => [
        'enabled' => (bool) env('INCENTIVES_CITY_LEAGUE', true),

        // Seasons are calendar months in this timezone.
        'timezone' => 'Europe/Madrid',

        // Score = Σ count × weight. In-app, verified actions only.
        'weights' => [
            'kolabs_completed' => 40,
            'checkins' => 2,
            'repeat_attendees' => 5,
            'venue_reviews_5star' => 20,
        ],

        // Divisions by active members across the organiser's communities:
        // small < medium_from ≤ medium < large_from ≤ large. Divisions only
        // exist when at least two of them have this many organisers with
        // points this month; otherwise the whole city is one division.
        'divisions' => [
            'medium_from' => 50,
            'large_from' => 200,
            'min_active_per_division' => 8,
        ],

        // Once divisions exist: top N move up, bottom N move down next season.
        'promotion_slots' => 3,

        // "Top of the division" for the Top organiser level.
        'top_ranks' => 3,
        'top_percent' => 0.10,

        // Division winners of last season get this much extra venue-side
        // ranking during the next month.
        'champion_discovery_points' => 5,

        // /me/community-rank preview: top N + viewer ± neighbours.
        'preview_top' => 3,
        'preview_neighbours' => 1,

        'top_reward' => 'Top organisers get personal intros to sports brands, fashion brands and venues',

        'division_labels' => [
            'city' => 'City',
            'small' => 'Division 3 (under 50 members)',
            'medium' => 'Division 2 (50 to 199 members)',
            'large' => 'Division 1 (200+ members)',
        ],
    ],
];
