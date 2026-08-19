<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use App\Services\Suggestions\SuggestionGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * The nightly two-sided suggestion pass (BE-NF-28). Scheduled at 04:00 in
 * `routes/console.php`.
 *
 * Attendees are excluded at the query rather than left to the generator: they
 * are never an audience, and on production they are the largest of the three
 * profile types, so scoring them would be the bulk of the batch producing
 * nothing.
 *
 * Every profile is processed inside its own try/catch. The pass covers the whole
 * platform, so one profile whose data breaks a scorer invariant must cost one
 * profile — `report()` sends it to Sentry and the batch continues. Per-*pair*
 * isolation is one level down, in `SuggestionGenerator` and
 * `PairCandidateFinder`.
 */
class GenerateSuggestions extends Command
{
    protected $signature = 'app:generate-suggestions
        {--profile= : Only this profile id}
        {--dry-run : Score and report without writing}';

    protected $description = 'Score candidate collaboration pairs and refresh each profile\'s suggestions';

    public function handle(SuggestionGenerator $generator): int
    {
        if (! config('suggestions.enabled')) {
            $this->info('Suggestion generation is disabled (suggestions.enabled is false). Nothing was written.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $profileId = $this->option('profile');

        $query = Profile::query()
            ->whereIn('user_type', [UserType::Business->value, UserType::Community->value]);

        if (is_string($profileId) && trim($profileId) !== '') {
            $query->whereKey(trim($profileId));
        }

        $profiles = 0;
        $written = 0;
        $failed = 0;

        $query->chunkById(200, function (Collection $chunk) use ($generator, $dryRun, &$profiles, &$written, &$failed): void {
            foreach ($chunk as $profile) {
                $profiles++;

                try {
                    $written += $generator->generateFor($profile, $dryRun);
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                    $this->warn("Skipped profile {$profile->getKey()}: {$e->getMessage()}");
                }
            }
        });

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info("{$prefix}Suggestions written: {$written} across {$profiles} profiles ({$failed} failed).");

        return self::SUCCESS;
    }
}
