<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------
    | Feature flag
    |--------------------------------------------------------------------
    |
    | Gates the generation command, the API endpoints and the web nav entry.
    | Ships false so the backend can run a batch on production data and be
    | inspected before anyone sees a card. See the design spec section 3.8.
    |
    */
    'enabled' => (bool) env('SUGGESTIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------
    |
    | Weights sum to 1.0 and are renormalised over the signals that actually
    | have data behind them (see SignalScorer::score). `min_score` is the
    | floor below which a pair is not written at all — better an empty state
    | than a bad suggestion. `momentum_window_days` and `max_distance_km` are
    | the two scorer inputs that are measured rather than weighted: how far
    | back the momentum signal looks, and the distance at which location fit
    | reaches zero. All of these are first guesses to be tuned against the
    | first real batch; tuning is a config change, not a code change.
    |
    */
    'weights' => [
        'category_fit' => 0.25,
        'location_fit' => 0.15,
        'scale_fit' => 0.15,
        'offer_need_fit' => 0.20,
        'delivery_proof' => 0.15,
        'momentum' => 0.10,
    ],

    'min_score' => 45,

    'confidence_thresholds' => [
        'high' => 0.75,
        'medium' => 0.45,
    ],

    'momentum_window_days' => 90,
    'max_distance_km' => 60,

    /*
    | The share of a community's declared size we expect to actually turn up,
    | used only when there are no reported attendance figures to take a median
    | of. It sets a number a user reads on a card ("expect around 30 people"),
    | so it belongs here with the other measured inputs rather than in code: a
    | first guess, to be tuned against the first real batch. Set too low to
    | round to a whole person and the fallback yields no number at all, which is
    | the correct degradation — see SignalScorer::expectedAttendance().
    */
    'community_size_attendance_fraction' => 0.25,

    /*
    |--------------------------------------------------------------------
    | Generation
    |--------------------------------------------------------------------
    |
    | How many suggestions the nightly pass keeps per profile, how long a row
    | stays live before it ages out, and how long a dismissal suppresses a
    | pair. The unique key on `kolab_suggestions` excludes `batch_key`, so the
    | pass refreshes rows in place rather than accumulating one per night.
    |
    */
    'per_profile' => 5,
    'expires_after_days' => 14,
    'dismissal_cooldown_days' => 60,

    /*
    |--------------------------------------------------------------------
    | Digest
    |--------------------------------------------------------------------
    |
    | The weekly email. `per_email` caps how many cards one message carries,
    | `resend_after_days` keeps a profile from being mailed again too soon,
    | and `templates` is keyed by SuggestionAudience value so the sender can
    | look a template up from the audience without a match statement.
    |
    */
    'digest' => [
        'per_email' => 3,
        'resend_after_days' => 6,
        'templates' => [
            'business' => 'suggestion-digest-business',
            'community' => 'suggestion-digest-community',
        ],
    ],

];
