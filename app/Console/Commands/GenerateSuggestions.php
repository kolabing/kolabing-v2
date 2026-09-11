<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\Suggestions\SuggestionGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The nightly two-sided suggestion pass (BE-NF-39). Scheduled at 04:00 in
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
 * `PairCandidateFinder`, and the pairs those two drop are counted back up to
 * here: `written: 0` on its own cannot tell an empty platform from a batch that
 * lost every pair to a failure.
 *
 * The pass also prunes, unconditionally rather than behind a `--prune` flag.
 * Nothing else ever deletes from this table, so it grows as
 * `viewers x counterparts ever scored` — `expires_at` only hides a dead row. A
 * prune that has to be remembered as a flag is a prune that never runs, and this
 * command is the table's only scheduled writer, so retention belongs in the same
 * pass that creates the rows. The rule is deliberately conservative; see
 * {@see self::prune()}.
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
            $profileId = trim($profileId);

            /**
             * `whereKey()` on a non-uuid string is a Postgres `22P02` — an
             * uncatchable syntax error that kills the whole command — while
             * SQLite happily compares it as text and matches nothing. The test
             * suite runs on SQLite and therefore cannot see the difference, which
             * is exactly the BE-FX-12 divergence this repo has been burned by
             * twice, so the value is validated in PHP before it reaches SQL.
             */
            if (! Str::isUuid($profileId)) {
                $this->error("[{$profileId}] is not a valid profile id (expected a uuid).");

                return self::FAILURE;
            }

            $query->whereKey($profileId);
        }

        $profiles = 0;
        $written = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(200, function (Collection $chunk) use ($generator, $dryRun, &$profiles, &$written, &$skipped, &$failed): void {
            foreach ($chunk as $profile) {
                $profiles++;

                try {
                    $result = $generator->generateFor($profile, $dryRun);
                    $written += $result['written'];
                    $skipped += $result['skipped'];
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                    $this->warn("Skipped profile {$profile->getKey()}: {$e->getMessage()}");
                }
            }
        });

        $pruned = $this->prune($dryRun);

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info("{$prefix}Suggestions written: {$written} across {$profiles} profiles ({$failed} profiles failed).");

        if ($skipped > 0) {
            $this->warn("{$prefix}{$skipped} pair(s) were skipped after a failure — see the suggestion warnings in the log.");
        }

        $this->info("{$prefix}Expired suggestions pruned: {$pruned}.");

        return self::SUCCESS;
    }

    /**
     * Delete rows that have been dead long enough to be worthless, and nothing
     * else. Three exclusions, each protecting something the feature cannot
     * reconstruct:
     *
     * - a row with `converted_kolab_id` is never deleted at any age. It is the
     *   only record that a suggestion became a real Kolab, which is the entire
     *   measurement story for this feature.
     * - a row whose `dismissed_at` is inside the cooldown is never deleted:
     *   deleting it would drop the suppression and re-suggest the pair the
     *   following night, which is the one thing a dismissal must prevent.
     * - everything else has to have been expired for longer than the cooldown
     *   window before it goes, so a row is never removed while any rule still
     *   depends on it.
     *
     * Day granularity on the dismissal boundary, matching every other window in
     * this feature.
     */
    private function prune(bool $dryRun): int
    {
        $cooldownDays = (int) config('suggestions.dismissal_cooldown_days');

        $prunable = KolabSuggestion::query()
            ->whereNull('converted_kolab_id')
            ->where('expires_at', '<', Carbon::now()->subDays($cooldownDays))
            ->where(function ($stale) use ($cooldownDays): void {
                $stale->whereNull('dismissed_at')
                    ->orWhere('dismissed_at', '<', Carbon::today()->subDays($cooldownDays));
            });

        if ($dryRun) {
            return $prunable->count();
        }

        return $prunable->delete();
    }
}
