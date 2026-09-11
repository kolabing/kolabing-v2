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
Schedule::command('app:recalculate-partner-statuses')->dailyAt('14:20');

// Generate two-sided collaboration suggestions (BE-NF-39). Scores candidate
// pairs in PHP and refreshes each profile's top N rows in place — the unique key
// is (viewer, counterpart) and excludes batch_key, so re-runs never accumulate
// cards. 04:00 is the only free nightly slot (02:00 tiers, 03:00 auto-complete,
// 08:00/09:00 reminders, 14:20 partner statuses). Gated on suggestions.enabled.
Schedule::command('app:generate-suggestions')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Onboarding email drip (T+0 welcome / T+2 complete-profile / T+5 activation /
// T+10 inactive-nudge, see config/onboarding_drip.php). Polls due drip-state
// rows hourly; each step re-checks eligibility at send time. New signups are
// enrolled at registration (AuthService::startOnboardingDrip); run the command
// once with --sync-new to backfill profiles created before that hook existed.
Schedule::command('app:send-onboarding-drip')
    ->hourly()
    ->withoutOverlapping();

/*
 * A challenge request nobody answered dies with its event (kolabing-app#154).
 * Nightly rather than hourly: the read paths already refuse an expired request,
 * so this only tidies the data, and there is nothing to race.
 */
Schedule::command('app:expire-pending-challenges')->dailyAt('03:30');
