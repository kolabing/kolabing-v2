<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Services\CollaborationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoCompleteStaleCollaborations extends Command
{
    protected $signature = 'app:auto-complete-stale-collaborations {--dry-run : Report what would be completed without writing}';

    protected $description = 'Auto-complete scheduled/active collaborations where the first party confirmed (left feedback) more than the configured grace window ago, and the partner has not confirmed.';

    public function handle(CollaborationService $collaborations): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceDays = (int) config('collaborations.auto_complete_grace_days_after_first_feedback', 3);

        // Mutual-confirm with a grace timer: the clock starts at the FIRST
        // feedback row. "Any feedback older than the cutoff" == "the first
        // feedback is older than the cutoff", so a single subquery selects every
        // collab whose grace window has elapsed.
        $cutoff = Carbon::now()->subDays($graceDays);

        $candidates = Collaboration::query()
            ->whereIn('status', [
                CollaborationStatus::Scheduled->value,
                CollaborationStatus::Active->value,
            ])
            ->whereIn('id', CollaborationFeedback::query()
                ->where('created_at', '<=', $cutoff)
                ->select('collaboration_id'))
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No stale collaborations to auto-complete.');

            return self::SUCCESS;
        }

        $completed = 0;
        foreach ($candidates as $collaboration) {
            if ($dryRun) {
                $this->line(sprintf('[dry] would auto-complete %s (scheduled %s)', $collaboration->id, $collaboration->scheduled_date?->toDateString() ?? '—'));
                $completed++;

                continue;
            }

            try {
                $collaborations->autoComplete($collaboration);
                $completed++;
            } catch (\Throwable $e) {
                $this->warn(sprintf('Skipped %s: %s', $collaboration->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('%s %d collaboration(s).', $dryRun ? '[dry] would complete' : 'Completed', $completed));

        return self::SUCCESS;
    }
}
