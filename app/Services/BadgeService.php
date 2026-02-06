<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BadgeMilestoneType;
use App\Enums\ChallengeCompletionStatus;
use App\Enums\NotificationType;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\ChallengeCompletion;
use App\Models\Profile;
use App\Models\RewardClaim;

class BadgeService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Check all milestone conditions and award any earned badges.
     *
     * @return array<Badge>
     */
    public function checkAndAwardBadges(Profile $profile): array
    {
        if (! $profile->isAttendee() || ! $profile->attendeeProfile) {
            return [];
        }

        $existingBadgeTypes = BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->join('badges', 'badges.id', '=', 'badge_awards.badge_id')
            ->pluck('badges.milestone_type')
            ->toArray();

        $badges = Badge::all();
        $awarded = [];

        foreach ($badges as $badge) {
            if (in_array($badge->milestone_type->value, $existingBadgeTypes, true)) {
                continue;
            }

            if ($this->isMilestoneReached($profile, $badge)) {
                BadgeAward::query()->create([
                    'badge_id' => $badge->id,
                    'profile_id' => $profile->id,
                    'awarded_at' => now(),
                ]);
                $awarded[] = $badge;

                $this->notificationService->createNotification(
                    recipient: $profile,
                    type: NotificationType::BadgeAwarded,
                    title: 'Badge Earned!',
                    body: "You earned the \"{$badge->name}\" badge!",
                    targetId: $badge->id,
                    targetType: 'badge',
                );
            }
        }

        return $awarded;
    }

    private function isMilestoneReached(Profile $profile, Badge $badge): bool
    {
        $ap = $profile->attendeeProfile;

        return match ($badge->milestone_type) {
            BadgeMilestoneType::FirstCheckin => $ap->total_events_attended >= 1,
            BadgeMilestoneType::FirstChallenge => $ap->total_challenges_completed >= 1,
            BadgeMilestoneType::SocialButterfly => $this->getUniqueVerifierCount($profile) >= $badge->milestone_value,
            BadgeMilestoneType::ChallengeMaster => $ap->total_challenges_completed >= $badge->milestone_value,
            BadgeMilestoneType::EventGuru => $ap->total_events_attended >= $badge->milestone_value,
            BadgeMilestoneType::PointHunter => $ap->total_points >= $badge->milestone_value,
            BadgeMilestoneType::Legend => $ap->total_points >= $badge->milestone_value,
            BadgeMilestoneType::RewardCollector => $this->getRewardsWonCount($profile) >= $badge->milestone_value,
            BadgeMilestoneType::LoyalAttendee => $ap->total_events_attended >= $badge->milestone_value,
        };
    }

    private function getUniqueVerifierCount(Profile $profile): int
    {
        return ChallengeCompletion::query()
            ->where('challenger_profile_id', $profile->id)
            ->where('status', ChallengeCompletionStatus::Verified)
            ->distinct('verifier_profile_id')
            ->count('verifier_profile_id');
    }

    private function getRewardsWonCount(Profile $profile): int
    {
        return RewardClaim::query()
            ->where('profile_id', $profile->id)
            ->count();
    }
}
