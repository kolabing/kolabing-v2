<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Collaboration;
use Illuminate\Console\Command;

class BackfillLifecycleTimestamps extends Command
{
    protected $signature = 'app:backfill-lifecycle-timestamps {--dry-run : Show what would be updated without writing}';

    protected $description = 'One-off: copy updated_at into the matching transition timestamp on legacy rows. Approximate.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $report = [];

        $report['applications.accepted'] = $this->touch(
            Application::query()->where('status', 'accepted')->whereNull('accepted_at'),
            'accepted_at',
            $dryRun,
        );
        $report['applications.declined'] = $this->touch(
            Application::query()->where('status', 'declined')->whereNull('declined_at'),
            'declined_at',
            $dryRun,
        );
        $report['applications.withdrawn'] = $this->touch(
            Application::query()->where('status', 'withdrawn')->whereNull('withdrawn_at'),
            'withdrawn_at',
            $dryRun,
        );

        $report['collaborations.activated'] = $this->touch(
            Collaboration::query()
                ->whereIn('status', ['active', 'completed'])
                ->whereNull('activated_at'),
            'activated_at',
            $dryRun,
        );
        $report['collaborations.cancelled'] = $this->touch(
            Collaboration::query()->where('status', 'cancelled')->whereNull('cancelled_at'),
            'cancelled_at',
            $dryRun,
        );

        foreach ($report as $key => $count) {
            $this->line(sprintf('%s %s rows', $dryRun ? '[dry] would update' : 'updated', $count).' for '.$key);
        }

        return self::SUCCESS;
    }

    /**
     * Set the named timestamp column to the row's updated_at value for every row
     * the query matches.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function touch($query, string $column, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $query->count();
        }

        // SQLite + PostgreSQL both accept this raw expression.
        return $query->update([$column => $query->getModel()->getConnection()->raw('updated_at')]);
    }
}
