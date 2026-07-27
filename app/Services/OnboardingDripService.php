<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Models\OnboardingDripState;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Drives the T+0/T+2/T+5/T+10 onboarding email drip.
 *
 * Same architectural shape as {@see NotificationReminderService}: a per-recipient
 * state row (here {@see OnboardingDripState}, one per profile), a cadence-hours
 * offset table, and a due-polling sender that re-checks eligibility at send time
 * so a step is skipped/cancelled cleanly the moment its underlying condition
 * resolves (profile completed, first action taken).
 *
 * Cadence (config('onboarding_drip.cadence_hours'), recommended by Jace,
 * pending Daniel's sign-off — see config/onboarding_drip.php):
 *   0. T+0  Welcome                     — always
 *   1. T+2  Complete-profile nudge      — only if profile still incomplete
 *   2. T+5  Activation nudge            — only if no first action taken yet
 *   3. T+10 Inactive nudge              — only if still no first action taken
 *
 * Steps whose condition is no longer met when they come due are skipped (not
 * cancelled outright) so a later step can still fire. The welcome (step 0)
 * always sends; from step 1 on, the whole drip is cancelled the moment the
 * profile is fully activated (see sendDue()/processDueRow()).
 */
class OnboardingDripService
{
    private const STEP_WELCOME = 0;

    private const STEP_COMPLETE_PROFILE = 1;

    private const STEP_ACTIVATION = 2;

    private const STEP_INACTIVE_NUDGE = 3;

    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    /**
     * Enrol a profile into the drip. One-shot and idempotent: once a profile
     * has a state row — running, completed, or cancelled — this is a no-op.
     * Onboarding happens once; resurrecting a finished drip would re-send the
     * welcome.
     *
     * The cadence is anchored on enrolment time (now()), not the profile's
     * created_at, so a profile backfilled via --sync-new well after signup
     * still receives the full T+0..T+10 sequence from the backfill moment
     * rather than having every past-due step collapsed away by advance().
     */
    public function startForProfile(Profile $profile): void
    {
        $state = OnboardingDripState::query()->firstOrNew(['profile_id' => $profile->id]);

        if ($state->exists) {
            return;
        }

        $cadenceHours = $this->cadenceHours();
        $anchor = now();

        $state->fill([
            'anchor_at' => $anchor,
            'next_sequence' => 0,
            'last_sent_sequence' => null,
            'scheduled_for' => $anchor->copy()->addHours($cadenceHours[0]),
            'sent_at' => null,
            'cancelled_at' => null,
        ])->save();
    }

    /**
     * Cancel the drip outright (e.g. profile deleted/banned). Distinct from a
     * single step being skipped for no longer being eligible.
     */
    public function cancelForProfile(Profile $profile): void
    {
        OnboardingDripState::query()
            ->where('profile_id', $profile->id)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now(), 'scheduled_for' => null]);
    }

    /**
     * Process every due drip step across all profiles. Returns the count of
     * emails actually dispatched (skipped/ineligible steps are advanced past,
     * not counted).
     */
    public function sendDue(int $limit = 200): int
    {
        $dueIds = OnboardingDripState::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id');

        $sentCount = 0;

        foreach ($dueIds as $id) {
            $sentCount += $this->processDueRow($id);
        }

        return $sentCount;
    }

    /**
     * Process a single due drip row under a row lock so two overlapping runs
     * (or a retried job) can never dispatch the same step twice: the row is
     * re-read + re-checked inside the transaction, and a concurrent pass that
     * already advanced it sees a future/null scheduled_for and bails.
     *
     * @return int 1 if an email was dispatched, 0 otherwise.
     */
    private function processDueRow(string $id): int
    {
        return DB::transaction(function () use ($id): int {
            $state = OnboardingDripState::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->with('profile.businessProfile', 'profile.communityProfile', 'profile.attendeeProfile')
                ->first();

            if ($state === null
                || $state->cancelled_at !== null
                || $state->scheduled_for === null
                || $state->scheduled_for->gt(now())) {
                return 0;
            }

            $profile = $state->profile;

            if ($profile === null) {
                $this->cancel($state);

                return 0;
            }

            // Stop nudging a fully-activated user — but the T+0 welcome always
            // sends; only the later nudge steps are short-circuited.
            if ($state->next_sequence > self::STEP_WELCOME && $this->isFullyActivated($profile)) {
                $this->cancel($state);

                return 0;
            }

            $dispatched = $this->dispatchStep($profile, $state->next_sequence);

            $this->advance($state, $dispatched);

            return $dispatched ? 1 : 0;
        });
    }

    /**
     * Send the given step's email if still eligible. Returns whether an email
     * was actually dispatched (false = step skipped, condition no longer met).
     */
    private function dispatchStep(Profile $profile, int $sequence): bool
    {
        return match ($sequence) {
            self::STEP_WELCOME => $this->sendWelcome($profile),
            self::STEP_COMPLETE_PROFILE => $this->sendCompleteProfileNudge($profile),
            self::STEP_ACTIVATION => $this->sendActivationNudge($profile),
            self::STEP_INACTIVE_NUDGE => $this->sendInactiveNudge($profile),
            default => false,
        };
    }

    private function sendWelcome(Profile $profile): bool
    {
        $alias = match ($profile->user_type) {
            UserType::Business => 'business-welcome-01',
            UserType::Community => 'community-welcome-01',
            UserType::Attendee => 'attendee-welcome-01',
        };

        return $this->emailService->send($profile, $alias, [
            'first_name' => $this->displayName($profile),
        ], EmailService::CATEGORY_NUDGE);
    }

    private function sendCompleteProfileNudge(Profile $profile): bool
    {
        if ($profile->onboardingCompleted()) {
            return false;
        }

        // Attendee onboarding is completed at signup time (name/handle/city/interests
        // captured then) and has no dedicated "complete profile" template; only
        // business/community have a two-step (signup -> flesh out profile) flow.
        $alias = match ($profile->user_type) {
            UserType::Business => 'complete-profile-business',
            UserType::Community => 'complete-profile-community',
            UserType::Attendee => null,
        };

        if ($alias === null) {
            return false;
        }

        return $this->emailService->send($profile, $alias, [
            'first_name' => $this->displayName($profile),
        ], EmailService::CATEGORY_NUDGE);
    }

    private function sendActivationNudge(Profile $profile): bool
    {
        if ($this->hasTakenFirstAction($profile)) {
            return false;
        }

        $alias = match ($profile->user_type) {
            UserType::Business => 'activation-business',
            UserType::Community => 'activation-community',
            UserType::Attendee => 'attendee-activation-01',
        };

        return $this->emailService->send($profile, $alias, [
            'first_name' => $this->displayName($profile),
        ], EmailService::CATEGORY_NUDGE);
    }

    private function sendInactiveNudge(Profile $profile): bool
    {
        if ($this->hasTakenFirstAction($profile)) {
            return false;
        }

        return $this->emailService->send($profile, 'inactive-nudge', [
            'first_name' => $this->displayName($profile),
        ], EmailService::CATEGORY_NUDGE);
    }

    /**
     * "First action" per user type, mirrors the plan doc's activation-nudge
     * trigger (docs/plans/2026-06-04-transactional-email-system.md, catalog B):
     * business = published a Kolab; community = submitted an application;
     * attendee = joined a community.
     */
    private function hasTakenFirstAction(Profile $profile): bool
    {
        return match ($profile->user_type) {
            UserType::Business => $profile->kolabs()->where('status', KolabStatus::Published)->exists(),
            UserType::Community => $profile->applications()->where('status', '!=', ApplicationStatus::Withdrawn)->exists(),
            UserType::Attendee => $profile->communityMemberships()->exists(),
        };
    }

    private function isFullyActivated(Profile $profile): bool
    {
        return $profile->onboardingCompleted() && $this->hasTakenFirstAction($profile);
    }

    private function cancel(OnboardingDripState $state): void
    {
        $state->update(['scheduled_for' => null, 'cancelled_at' => now()]);
    }

    /**
     * Advance the state past the step just processed. sent_at and
     * last_sent_sequence are only touched when an email was actually
     * dispatched ($dispatched), so a skipped/ineligible step never records a
     * phantom "sent". Steps whose scheduled time is already in the past (a
     * scheduler outage / backfill catch-up) are skipped over so only the most
     * relevant current step fires, not a burst of stale ones.
     */
    private function advance(OnboardingDripState $state, bool $dispatched): void
    {
        $cadenceHours = $this->cadenceHours();
        $currentSequence = $state->next_sequence;
        $nextSequence = $currentSequence + 1;
        $now = now();

        while ($nextSequence < count($cadenceHours)
            && $state->anchor_at->copy()->addHours($cadenceHours[$nextSequence])->lte($now)) {
            $nextSequence++;
        }

        $state->update([
            'last_sent_sequence' => $dispatched ? $currentSequence : $state->last_sent_sequence,
            'next_sequence' => $nextSequence,
            'scheduled_for' => $nextSequence < count($cadenceHours)
                ? $state->anchor_at->copy()->addHours($cadenceHours[$nextSequence])
                : null,
            'sent_at' => $dispatched ? $now : $state->sent_at,
            'cancelled_at' => $nextSequence < count($cadenceHours) ? null : $now,
        ]);
    }

    /**
     * @return list<int>
     */
    private function cadenceHours(): array
    {
        return config('onboarding_drip.cadence_hours');
    }

    private function displayName(Profile $profile): ?string
    {
        return match ($profile->user_type) {
            UserType::Business => $profile->businessProfile?->name,
            UserType::Community => $profile->communityProfile?->name,
            UserType::Attendee => $profile->name,
        };
    }
}
