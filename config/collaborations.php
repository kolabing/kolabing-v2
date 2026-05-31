<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Feedback gate on /complete
    |--------------------------------------------------------------------------
    |
    | When true (default), POST /api/v1/collaborations/{id}/complete refuses to
    | transition status -> completed until both participants have a
    | collaboration_feedback row. Soft-rollout knob: flip via env var
    | COLLABORATIONS_COMPLETE_REQUIRES_FEEDBACK=false if a mobile cutover
    | regresses and you need the gate temporarily off.
    |
    */
    'complete_requires_feedback' => env('COLLABORATIONS_COMPLETE_REQUIRES_FEEDBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-completion window
    |--------------------------------------------------------------------------
    |
    | The scheduled command app:auto-complete-stale-collaborations completes
    | collabs where scheduled_date + days threshold has passed AND at least one
    | feedback row exists. Per Q9 in the 2026-06-01 feedback-gate plan.
    |
    */
    'auto_complete_threshold_days' => env('COLLABORATIONS_AUTO_COMPLETE_DAYS', 7),

    'auto_complete_requires_feedback_rows' => env('COLLABORATIONS_AUTO_COMPLETE_REQUIRES_FEEDBACK', true),
];
