<?php

declare(strict_types=1);

namespace App\Listeners\Gamification;

use App\Events\Gamification\BadgeAwarded;
use App\Events\Gamification\ChallengeRejected;
use App\Events\Gamification\ChallengeVerificationRequested;
use App\Events\Gamification\ChallengeVerified;
use App\Events\Gamification\RewardWon;
use App\Models\ChallengeCompletion;
use App\Models\Profile;
use App\Models\RewardClaim;
use App\Services\NotificationService;

class CreateGamificationNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof ChallengeVerificationRequested => $this->handleChallengeVerificationRequested($event),
            $event instanceof ChallengeVerified => $this->handleChallengeVerified($event),
            $event instanceof ChallengeRejected => $this->handleChallengeRejected($event),
            $event instanceof RewardWon => $this->handleRewardWon($event),
            $event instanceof BadgeAwarded => $this->handleBadgeAwarded($event),
            default => null,
        };
    }

    private function handleChallengeVerificationRequested(ChallengeVerificationRequested $event): void
    {
        $completion = ChallengeCompletion::query()->find($event->challengeCompletionId);

        if ($completion !== null) {
            $this->notificationService->notifyChallengeVerificationRequested($completion);
        }
    }

    private function handleChallengeVerified(ChallengeVerified $event): void
    {
        $completion = ChallengeCompletion::query()->find($event->challengeCompletionId);

        if ($completion !== null) {
            $this->notificationService->notifyChallengeVerified($completion);
        }
    }

    private function handleChallengeRejected(ChallengeRejected $event): void
    {
        $completion = ChallengeCompletion::query()->find($event->challengeCompletionId);

        if ($completion !== null) {
            $this->notificationService->notifyChallengeRejected($completion);
        }
    }

    private function handleRewardWon(RewardWon $event): void
    {
        $claim = RewardClaim::query()->find($event->rewardClaimId);

        if ($claim !== null) {
            $this->notificationService->notifyRewardWon($claim);
        }
    }

    private function handleBadgeAwarded(BadgeAwarded $event): void
    {
        $profile = Profile::query()->find($event->profileId);

        if ($profile !== null) {
            $this->notificationService->notifyBadgeAwardedByName(
                profile: $profile,
                badgeName: $event->badgeName,
                targetId: $event->targetId,
                targetType: $event->targetType,
                dedupeKey: $event->dedupeKey,
            );
        }
    }
}
