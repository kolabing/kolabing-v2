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
    | than a bad suggestion. Both are first guesses to be tuned against the
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

    /*
    |--------------------------------------------------------------------
    | Generation
    |--------------------------------------------------------------------
    */
    'per_profile' => 5,
    'expires_after_days' => 14,
    'dismissal_cooldown_days' => 60,
    'momentum_window_days' => 90,
    'max_distance_km' => 60,

    /*
    |--------------------------------------------------------------------
    | Digest
    |--------------------------------------------------------------------
    */
    'digest' => [
        'per_email' => 3,
        'resend_after_days' => 6,
        'template_business' => 'suggestion-digest-business',
        'template_community' => 'suggestion-digest-community',
    ],

];
