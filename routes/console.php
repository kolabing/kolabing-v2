<?php

use App\Jobs\Notifications\RetryFailedNotificationDeliveriesJob;
use App\Jobs\Notifications\SendGrowthCampaignNotificationsJob;
use App\Jobs\Notifications\SendScheduledNotificationJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendScheduledNotificationJob('24h'))->dailyAt('09:00');
Schedule::job(new SendScheduledNotificationJob('same_day'))->dailyAt('08:00');
Schedule::job(new SendGrowthCampaignNotificationsJob)->hourly();
Schedule::job(new RetryFailedNotificationDeliveriesJob)->hourly();
