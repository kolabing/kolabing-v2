<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Send collab-day reminders at 08:00 every morning.
// The command handles both day-of ("Today's your Kolab!") and
// follow-up ("Did it happen?") in one pass; dedup is done via
// the notifications table so re-runs are safe.
Schedule::command('app:send-collab-reminders')->dailyAt('08:00');

// Auto-complete collaborations stuck waiting on the second party's feedback
// past the configured threshold. See config/collaborations.php.
Schedule::command('app:auto-complete-stale-collaborations')->dailyAt('03:00');

// Promote community members into the highest auto-assignment tier they have
// earned (xp / tenure / events). Manual tiers are never auto-applied. On-check-in
// hooks handle immediate promotion; this nightly pass catches tenure rollovers.
Schedule::command('app:evaluate-community-tiers')->dailyAt('02:00');

// Nudge subscribed but inactive businesses back to the platform.
// Dedup is done via the notifications table so re-runs are safe.
Schedule::command('app:send-business-reactivation-reminders')->dailyAt('09:00');

// Recompute business partner_status from real collaboration history. Catches
// collaborations written directly to the database (e.g. seeded test data)
// that bypass CollaborationService's completion flow and never trigger
// recalculation. Idempotent (updateOrCreate) — safe to run daily.
Schedule::command('app:recalculate-partner-statuses')->dailyAt('04:00');
