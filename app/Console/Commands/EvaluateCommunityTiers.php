<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Community;
use App\Services\TierAssignmentService;
use Illuminate\Console\Command;

class EvaluateCommunityTiers extends Command
{
    protected $signature = 'app:evaluate-community-tiers {--dry-run : Report promotions without writing}';

    protected $description = 'Promote community members to the highest-rank tier whose auto-assignment rule (xp/tenure/events) they satisfy. Manual tiers are never auto-applied.';

    public function handle(TierAssignmentService $tiers): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalChanged = 0;
        $communities = 0;

        Community::query()->chunkById(100, function ($chunk) use ($tiers, $dryRun, &$totalChanged, &$communities): void {
            foreach ($chunk as $community) {
                $communities++;

                if ($dryRun) {
                    $would = $this->countWouldChange($community, $tiers);
                    if ($would > 0) {
                        $this->line(sprintf('[dry] %s: would promote %d member(s)', $community->name, $would));
                    }

                    continue;
                }

                $changed = $tiers->evaluateCommunity($community);
                $totalChanged += $changed;

                if ($changed > 0) {
                    $this->line(sprintf('%s: promoted %d member(s)', $community->name, $changed));
                }
            }
        });

        $this->info(sprintf(
            '%s %d community(ies), %d promotion(s).',
            $dryRun ? '[dry] evaluated' : 'Evaluated',
            $communities,
            $totalChanged
        ));

        return self::SUCCESS;
    }

    /**
     * Count members whose tier would change, without persisting, by evaluating
     * inside a transaction that is always rolled back.
     */
    private function countWouldChange(Community $community, TierAssignmentService $tiers): int
    {
        $count = 0;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($community, $tiers, &$count): void {
                $count = $tiers->evaluateCommunity($community);
                throw new DryRunRollback;
            });
        } catch (DryRunRollback) {
            // Intentional rollback — nothing was persisted.
        }

        return $count;
    }
}

/**
 * Internal sentinel used to roll back a dry-run evaluation transaction.
 */
class DryRunRollback extends \RuntimeException {}
