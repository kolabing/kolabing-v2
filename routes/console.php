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
