<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use App\Services\BusinessPartnerStatusService;
use Illuminate\Console\Command;

class RecalculatePartnerStatuses extends Command
{
    protected $signature = 'app:recalculate-partner-statuses
        {--profile= : Only recalculate this profile ID}
        {--dry-run : Report resulting statuses without writing}';

    protected $description = 'Recompute business partner_status from real collaboration history. '
        .'Needed for businesses whose completed collaborations were written directly to the '
        .'database (e.g. seeded test data) and never passed through the completion flow that '
        .'normally triggers recalculation.';

    public function handle(BusinessPartnerStatusService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $profileId = $this->option('profile');

        $query = Profile::query()->where('user_type', UserType::Business);

        if ($profileId !== null) {
            $query->where('id', $profileId);
        }

        $changed = 0;
        $total = 0;

        $query->chunkById(100, function ($chunk) use ($service, $dryRun, &$changed, &$total): void {
            foreach ($chunk as $profile) {
                $total++;
                $previous = $service->statusFor($profile);
                $new = $dryRun
                    ? $this->previewStatus($service, $profile)
                    : $service->recalculate($profile);

                if ($new !== $previous) {
                    $changed++;
                    $this->line(sprintf(
                        '%s[%s] %s: %s -> %s',
                        $dryRun ? '[dry] ' : '',
                        $profile->id,
                        $profile->name ?? 'unnamed',
                        $previous->value,
                        $new->value,
                    ));
                }
            }
        });

        $this->info(sprintf(
            '%s %d business profile(s), %d status change(s).',
            $dryRun ? 'Evaluated' : 'Recalculated',
            $total,
            $changed,
        ));

        return self::SUCCESS;
    }

    /**
     * Compute what the status would become without persisting, by running
     * recalculate() inside a transaction that is always rolled back.
     */
    private function previewStatus(BusinessPartnerStatusService $service, Profile $profile): \App\Enums\PartnerStatusTier
    {
        $result = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($service, $profile, &$result): void {
                $result = $service->recalculate($profile);
                throw new PartnerStatusDryRunRollback;
            });
        } catch (PartnerStatusDryRunRollback) {
            // Intentional rollback — nothing was persisted.
        }

        return $result;
    }
}

/**
 * Internal sentinel used to roll back a dry-run recalculation transaction.
 */
class PartnerStatusDryRunRollback extends \RuntimeException {}
