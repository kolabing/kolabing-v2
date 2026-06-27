<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Completion-confirmation gate on /complete
    |--------------------------------------------------------------------------
    |
    | When true (default), POST /api/v1/collaborations/{id}/complete refuses
    | to transition status -> completed until both participants have
    | submitted a yes/no/not_yet collaboration_completions row AND both said
    | 'yes'. Replaces the old rich-feedback gate as of the 2026-06-26
    | completion-flow simplification (PR 1) — feedback is now optional impact
    | data, not a completion requirement. Soft-rollout knob: flip via env var
    | COLLABORATIONS_COMPLETE_REQUIRES_COMPLETION_CONFIRMATION=false if a
    | mobile cutover regresses and you need the gate temporarily off.
    |
    */
    'complete_requires_completion_confirmation' => env('COLLABORATIONS_COMPLETE_REQUIRES_COMPLETION_CONFIRMATION', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-completion grace timer (mutual-confirm)
    |--------------------------------------------------------------------------
    |
    | Completion stays MUTUAL: a kolab marks completed once BOTH parties
    | confirm completion. But the clock starts when the FIRST party confirms
    | — the other then has this many days to confirm, after which
    | app:auto-complete-stale-collaborations auto-completes it (unless
    | someone explicitly answered 'no'). The grace window is measured from
    | the earliest collaboration_completions row, NOT from scheduled_date.
    | Default 3 days (Daniel, 2026-06-22; re-pointed to completions 2026-06-26).
    |
    */
    'auto_complete_grace_days_after_first_completion_confirmation' => (int) env('COLLABORATIONS_AUTO_COMPLETE_GRACE_DAYS', 3),
];
