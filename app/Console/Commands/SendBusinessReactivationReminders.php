<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\UserType;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBusinessReactivationReminders extends Command
{
    protected $signature = 'app:send-business-reactivation-reminders
        {--dry-run : Log what would be sent without actually sending}';

    protected $description = 'Nudge subscribed but inactive businesses back to Kolabing';

    public function handle(NotificationService $notificationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $inactivityDays = (int) config('gamification_business.reactivation_inactivity_days');
        $resendAfterDays = (int) config('gamification_business.reactivation_resend_after_days');
        $cutoff = Carbon::now()->subDays($inactivityDays);

        $candidates = Profile::query()
            ->where('user_type', UserType::Business)
            ->with('subscription')
            ->get();

        $sentCount = 0;

        foreach ($candidates as $business) {
            if (! $business->hasActiveSubscription()) {
                continue;
            }

            $lastActivity = $this->lastActivityAt($business);

            if ($lastActivity !== null && $lastActivity->isAfter($cutoff)) {
                continue;
            }

            if ($this->reminderSentRecently($business, $resendAfterDays)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] Would send reactivation reminder to business {$business->id}");

                continue;
            }

            $notificationService->notifyReactivation($business);
            $sentCount++;
        }

        $this->info("Reactivation reminders sent: {$sentCount}.");

        return self::SUCCESS;
    }

    /**
     * The most recent Kolab or collaboration activity for a business profile.
     * There is no dedicated "last active" column on profiles, so this is
     * derived from related activity — see the implementation plan's note
     * on this being an approximation, not a true session/login signal.
     */
    private function lastActivityAt(Profile $business): ?Carbon
    {
        $lastKolabActivity = Kolab::query()
            ->where('creator_profile_id', $business->id)
            ->max('updated_at');

        $lastCollaborationActivity = Collaboration::query()
            ->where(function ($query) use ($business): void {
                $query->where('creator_profile_id', $business->id)
                    ->orWhere('applicant_profile_id', $business->id);
            })
            ->max('updated_at');

        $timestamps = array_filter([
            $business->updated_at,
            $lastKolabActivity !== null ? Carbon::parse($lastKolabActivity) : null,
            $lastCollaborationActivity !== null ? Carbon::parse($lastCollaborationActivity) : null,
        ]);

        if ($timestamps === []) {
            return null;
        }

        return collect($timestamps)->max();
    }

    private function reminderSentRecently(Profile $business, int $resendAfterDays): bool
    {
        return Notification::query()
            ->where('profile_id', $business->id)
            ->where('type', NotificationType::ReactivationPrompt)
            ->where('created_at', '>=', Carbon::now()->subDays($resendAfterDays))
            ->exists();
    }
}
