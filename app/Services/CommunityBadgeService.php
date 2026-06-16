<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\CommunityBadgeCriteriaType;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\CommunityBadgeAward;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\EventCheckin;

/**
 * Auto-awards per-community badges. Strategy: recompute-on-event AND
 * recompute-on-read. evaluate() is idempotent (unique award row per
 * badge+profile), so calling it from earn hooks and from the rewards-hub read
 * both converge to the same set with no double-awards.
 */
class CommunityBadgeService
{
    /**
     * Evaluate every active badge of a community for one member and persist any
     * newly earned awards. Returns the ids of badges the member now holds.
     *
     * @return array<int, string> earned community_badge_id list
     */
    public function evaluate(Community $community, string $profileId): array
    {
        $badges = $community->badges()->where('is_active', true)->get();

        $earnedIds = CommunityBadgeAward::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->pluck('community_badge_id')
            ->all();

        foreach ($badges as $badge) {
            if (in_array($badge->id, $earnedIds, true)) {
                continue;
            }

            if ($this->meetsCriteria($community, $profileId, $badge)) {
                CommunityBadgeAward::query()->create([
                    'community_badge_id' => $badge->id,
                    'profile_id' => $profileId,
                    'community_id' => $community->id,
                    'awarded_at' => now(),
                ]);
                $earnedIds[] = $badge->id;
            }
        }

        return $earnedIds;
    }

    public function meetsCriteria(Community $community, string $profileId, CommunityBadge $badge): bool
    {
        $value = $badge->criteria_value;

        return match ($badge->criteria_type) {
            CommunityBadgeCriteriaType::PointsThreshold => $this->points($community, $profileId) >= $value,
            CommunityBadgeCriteriaType::EventCheckIns => $this->eventCheckIns($community, $profileId) >= $value,
            CommunityBadgeCriteriaType::DaysInCommunity => $this->daysInCommunity($community, $profileId) >= $value,
            CommunityBadgeCriteriaType::ChallengesCompleted => $this->challengesCompleted($community, $profileId, $badge) >= $value,
        };
    }

    public function badgeCount(Community $community, string $profileId): int
    {
        return CommunityBadgeAward::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->count();
    }

    private function points(Community $community, string $profileId): int
    {
        return (int) (CommunityPoints::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->value('points') ?? 0);
    }

    private function eventCheckIns(Community $community, string $profileId): int
    {
        return EventCheckin::query()
            ->where('profile_id', $profileId)
            ->whereIn('event_id', Event::query()
                ->where('community_id', $community->id)
                ->select('id'))
            ->count();
    }

    private function daysInCommunity(Community $community, string $profileId): int
    {
        $member = CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->first();

        if ($member === null || $member->joined_at === null) {
            return 0;
        }

        return (int) $member->joined_at->diffInDays(now());
    }

    private function challengesCompleted(Community $community, string $profileId, CommunityBadge $badge): int
    {
        $query = ChallengeCompletion::query()
            ->where('challenger_profile_id', $profileId)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->whereIn('event_id', Event::query()
                ->where('community_id', $community->id)
                ->select('id'));

        $challengeIds = $badge->challenge_ids ?? [];
        if ($challengeIds !== []) {
            $query->whereIn('challenge_id', $challengeIds);
        }

        return $query->count();
    }
}
