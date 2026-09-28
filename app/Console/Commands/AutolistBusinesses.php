<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use App\Services\BusinessAutoListingService;
use Illuminate\Console\Command;

/**
 * Backfill for BusinessAutoListingService: lists every existing active, real
 * (non-test, non-deleted) business that has no open offer in Explore, and
 * fills empty avatars from the top Google Maps photo.
 *
 * Dry run by default — prints what it would do. Pass --apply to write.
 */
class AutolistBusinesses extends Command
{
    protected $signature = 'kolabing:autolist-businesses {--apply : Write the listings and avatars (without this flag the command only reports)}';

    protected $description = 'Create the open-ended auto listing (and Maps-photo avatar) for existing businesses that have no open Kolab in Explore.';

    public function handle(BusinessAutoListingService $autoListing): int
    {
        $apply = (bool) $this->option('apply');
        $listed = 0;
        $wouldList = 0;
        $avatars = 0;
        $failed = 0;
        $skipped = [];

        $this->info($apply ? 'Auto-listing businesses…' : 'Dry run — nothing will be written. Pass --apply to commit.');

        Profile::query()
            ->where('user_type', UserType::Business)
            ->where('is_active', true)
            ->where('is_test_user', false)
            ->whereHas('businessProfile')
            ->with('businessProfile.city')
            ->chunkById(100, function ($profiles) use ($autoListing, $apply, &$listed, &$wouldList, &$avatars, &$failed, &$skipped): void {
                foreach ($profiles as $profile) {
                    $name = $profile->businessProfile?->name ?: $profile->email;
                    $avatar = $autoListing->avatarCandidate($profile);
                    $reason = $autoListing->skipReason($profile);

                    if ($avatar !== null) {
                        $avatars++;
                        $this->line("  avatar  {$profile->id}  {$name}  ← {$avatar}");
                    }

                    if ($reason !== null) {
                        $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
                    } else {
                        $wouldList++;
                        $payload = $autoListing->buildPayload($profile);
                        $this->line("  list    {$profile->id}  {$name}  [{$payload['intent_type']}, {$payload['preferred_city']}]  \"{$payload['title']}\"");
                    }

                    if (! $apply || ($avatar === null && $reason !== null)) {
                        continue;
                    }

                    $kolab = $autoListing->provisionSafely($profile);

                    if ($kolab !== null) {
                        $listed++;
                    } elseif ($reason === null) {
                        $failed++;
                        $this->error("  failed  {$profile->id}  {$name} (see log)");
                    }
                }
            });

        $this->newLine();
        $this->info($apply
            ? "Done. {$listed} listing(s) created, {$avatars} avatar(s) filled, {$failed} failed."
            : "Done. {$wouldList} listing(s) and {$avatars} avatar(s) would be written.");

        foreach ($skipped as $reason => $count) {
            $this->line("  skipped {$reason}: {$count}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
