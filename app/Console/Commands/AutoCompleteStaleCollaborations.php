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

    protected $description = 'Auto-complete scheduled/active collaborations whose scheduled_date passed the configured threshold and have at least one feedback row.';

    public function handle(CollaborationService $collaborations): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $thresholdDays = (int) config('collaborations.auto_complete_threshold_days', 7);
        $requireFeedback = (bool) config('collaborations.auto_complete_requires_feedback_rows', true);

        $cutoff = Carbon::now()->subDays($thresholdDays)->toDateString();

        $query = Collaboration::query()
            ->whereIn('status', [
                CollaborationStatus::Scheduled->value,
                CollaborationStatus::Active->value,
            ])
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '<=', $cutoff);

        if ($requireFeedback) {
            $query->whereIn('id', CollaborationFeedback::query()->select('collaboration_id'));
        }

        $candidates = $query->get();

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
