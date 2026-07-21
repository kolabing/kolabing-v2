<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use App\Services\OnboardingDripService;
use Illuminate\Console\Command;

/**
 * Onboarding email drip: T+0 welcome -> T+2 complete-profile nudge (if still
 * incomplete) -> T+5 activation nudge (if no first action) -> T+10 inactive
 * nudge (if still no first action). Cadence from config('onboarding_drip.cadence_hours').
 *
 * NOT LIVE. Built and registered so it's discoverable/testable
 * (`php artisan app:send-onboarding-drip`), but intentionally left OUT of
 * routes/console.php's scheduler — see the commented-out entry there.
 * Daniel has not signed off on the N-day offsets going live yet. Do not add
 * a Schedule::command(...) line for this without that sign-off.
 */
class SendOnboardingDrip extends Command
{
    protected $signature = 'app:send-onboarding-drip
        {--dry-run : Log what would happen without sending or writing state}
        {--sync-new : Also start drip state for profiles that signed up but have no state row yet}';

    protected $description = 'Send the T+0/T+2/T+5/T+10 onboarding email drip (NOT scheduled live yet, see routes/console.php)';

    public function handle(OnboardingDripService $drip): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('sync-new')) {
            $this->syncNewProfiles($drip, $dryRun);
        }

        if ($dryRun) {
            $this->info('[dry-run] Skipping send pass (state would be evaluated + advanced on a real run).');

            return self::SUCCESS;
        }

        $sentCount = $drip->sendDue();

        $this->info("Onboarding drip emails sent: {$sentCount}.");

        return self::SUCCESS;
    }

    /**
     * Backfill drip state for any profile that doesn't have one yet. In
     * steady state this would run from the registration seam
     * (AuthService::register*()); until that hook is added, --sync-new lets
     * this command pick up existing/new profiles on its own.
     */
    private function syncNewProfiles(OnboardingDripService $drip, bool $dryRun): void
    {
        $candidates = Profile::query()
            ->whereIn('user_type', UserType::values())
            ->whereDoesntHave('onboardingDripState')
            ->get();

        $this->info("Profiles without drip state: {$candidates->count()}.");

        foreach ($candidates as $profile) {
            if ($dryRun) {
                $this->line("  [dry-run] Would start onboarding drip for profile {$profile->id}");

                continue;
            }

            $drip->startForProfile($profile);
        }
    }
}
