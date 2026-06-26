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
    | Auto-completion grace timer (mutual-confirm)
    |--------------------------------------------------------------------------
    |
    | Completion stays MUTUAL: a kolab marks completed once BOTH parties submit
    | feedback. But the clock starts when the FIRST party confirms — the other
    | then has this many days to confirm, after which
    | app:auto-complete-stale-collaborations auto-completes it. So the grace
    | window is measured from the earliest collaboration_feedback row, NOT from
    | scheduled_date. Default 3 days (Daniel, 2026-06-22).
    |
    */
    'auto_complete_grace_days_after_first_feedback' => (int) env('COLLABORATIONS_AUTO_COMPLETE_GRACE_DAYS', 3),
];
