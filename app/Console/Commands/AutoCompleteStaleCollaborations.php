<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CollaborationCompletionStatus;
use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
use App\Services\CollaborationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoCompleteStaleCollaborations extends Command
{
    protected $signature = 'app:auto-complete-stale-collaborations {--dry-run : Report what would be completed without writing}';

    protected $description = 'Auto-complete scheduled/active collaborations where one party confirmed yes more than the configured grace window ago and the partner has not responded at all — never when anyone answered no/not_yet.';

    /**
     * Safest-MVP decision (2026-06-27 QA fix): auto-complete requires at
     * least one 'yes' confirmation older than the grace window, AND no
     * completion row of any status may be 'no' or 'not_yet' — from EITHER
     * party. This deliberately only covers "one party said yes, the other
     * has gone silent"; it intentionally does NOT auto-complete when the
     * silent/slow party has actually responded with 'not_yet' (an explicit
     * "ask me later" is a real signal, same as 'no') — those are left for
     * the participants or an admin to resolve manually.
     *
     * The grace window is measured from when the confirmation was last set
     * (updated_at), not from the row's original creation, so a 'not_yet' that
     * is later changed to 'yes' still gets a full grace window from the 'yes'.
     */
    public function handle(CollaborationService $collaborations): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceDays = (int) config('collaborations.auto_complete_grace_days_after_first_completion_confirmation', 3);

        $cutoff = Carbon::now()->subDays($graceDays);

        $candidates = Collaboration::query()
            ->whereIn('status', [
                CollaborationStatus::Scheduled->value,
                CollaborationStatus::Active->value,
            ])
            // A 'yes' confirmation set more than the grace window ago.
            ->whereIn('id', CollaborationCompletion::query()
                ->where('status', CollaborationCompletionStatus::Yes->value)
                ->where('updated_at', '<=', $cutoff)
                ->select('collaboration_id'))
            // Never auto-complete if anyone answered 'no' OR 'not_yet' — both
            // are real signals (the Kolab didn't happen / hasn't happened
            // yet), not silence, and must be resolved manually/by admin
            // rather than papered over.
            ->whereNotIn('id', CollaborationCompletion::query()
                ->whereIn('status', [
                    CollaborationCompletionStatus::No->value,
                    CollaborationCompletionStatus::NotYet->value,
                ])
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
