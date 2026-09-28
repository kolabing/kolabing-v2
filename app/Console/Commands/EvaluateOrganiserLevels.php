<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use App\Services\Incentives\OrganiserLevelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nightly organiser-level evaluation (incentives v1, C). Writes one snapshot
 * per community profile (calendar months, Europe/Madrid); levels go up and down.
 */
class EvaluateOrganiserLevels extends Command
{
    protected $signature = 'app:evaluate-organiser-levels {--profile= : Evaluate a single profile id}';

    protected $description = 'Recompute organiser levels (New/Rising/Trusted/Top) for community profiles';

    public function handle(OrganiserLevelService $levels): int
    {
        $query = Profile::query()->active()->where('user_type', UserType::Community->value);

        if ($id = $this->option('profile')) {
            $query->whereKey($id);
        }

        $counts = ['evaluated' => 0, 'up' => 0, 'down' => 0, 'failed' => 0];

        $query->orderBy('id')->chunkById(200, function ($profiles) use ($levels, &$counts): void {
            foreach ($profiles as $profile) {
                try {
                    $snapshot = $levels->evaluate($profile);
                    $counts['evaluated']++;

                    if ($snapshot->previous_level !== null && $snapshot->previous_level !== $snapshot->level) {
                        $snapshot->level->rank() > $snapshot->previous_level->rank() ? $counts['up']++ : $counts['down']++;
                    }
                } catch (Throwable $e) {
                    $counts['failed']++;
                    Log::warning('Organiser level evaluation failed', ['profile_id' => $profile->id, 'error' => $e->getMessage()]);
                }
            }
        });

        $this->info(sprintf('Evaluated %d organisers (%d up, %d down, %d failed).', $counts['evaluated'], $counts['up'], $counts['down'], $counts['failed']));

        return self::SUCCESS;
    }
}
