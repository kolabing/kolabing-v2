<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Collaboration;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendCollabReminders extends Command
{
    protected $signature = 'app:send-collab-reminders
        {--dry-run : Log what would be sent without actually sending}';

    protected $description = 'Send collab-day and follow-up reminders for scheduled collaborations';

    public function handle(NotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ─── Morning reminder: scheduled_date = today, not yet completed/cancelled ───
        $todayCollabs = Collaboration::query()
            ->whereDate('scheduled_date', $today)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['creatorProfile', 'applicantProfile'])
            ->get();

        $this->info("Day-of reminders: {$todayCollabs->count()} collab(s) scheduled for today.");

        foreach ($todayCollabs as $collab) {
            if ($dryRun) {
                $this->line("  [dry-run] Would send day-of reminder for collab {$collab->id}");
                continue;
            }
            $notificationService->notifyCollabDayReminder($collab);
        }

        // ─── Follow-up reminder: scheduled_date = yesterday, not completed/cancelled ───
        $followUpCollabs = Collaboration::query()
            ->whereDate('scheduled_date', $yesterday)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['creatorProfile', 'applicantProfile'])
            ->get();

        $this->info("Follow-up reminders: {$followUpCollabs->count()} collab(s) from yesterday still pending.");

        foreach ($followUpCollabs as $collab) {
            if ($dryRun) {
                $this->line("  [dry-run] Would send follow-up reminder for collab {$collab->id}");
                continue;
            }
            $notificationService->notifyCollabFollowUpReminder($collab);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
