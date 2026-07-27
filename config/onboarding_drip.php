<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------
    | Onboarding drip cadence (hours since signup)
    |--------------------------------------------------------------------
    |
    | Recommended cadence from Jace (Serra CMO), vault deliverable
    | _deliverables/to-review/jace/2026-07-20 - kolabing-launch-emails-and-onboarding.md,
    | task 1a: T+0 welcome -> T+2 complete-profile nudge (if incomplete) ->
    | T+5 activation nudge (if no first action) -> T+10 inactive nudge.
    |
    | NOT YET APPROVED BY DANIEL. Built and ready; do not enable the
    | scheduled command in routes/console.php until the offsets below are
    | signed off. See app/Console/Commands/SendOnboardingDrip.php.
    |
    */
    'cadence_hours' => [0, 48, 120, 240],

];
