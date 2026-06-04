<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\TierAssignmentRule;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\PointLedger;

/**
 * Evaluates the threshold-based tier rules and promotes members to the highest
 * rank tier whose rule they satisfy. Manual tiers (leader-assigned) are never
 * auto-applied and a member sitting on a non-default manual tier is never
 * auto-overwritten. Members are never demoted by this service.
 */
class TierAssignmentService
{
    /**
     * Re-evaluate a single member and promote if a higher auto tier is earned.
     */
    public function evaluateMember(CommunityMember $member): void
    {
        if ($member->status !== CommunityMemberStatus::Active) {
            return;
        }

        $currentTier = $member->tier;

        // A leader-placed manual tier above the default is sacred: never touch.
        if ($currentTier
            && $currentTier->assignment_rule === TierAssignmentRule::Manual
            && ! $currentTier->is_default
        ) {
            return;
        }

        $currentRank = $currentTier?->rank ?? PHP_INT_MIN;

        $earned = $this->highestEarnedTier($member);

        if ($earned !== null && $earned->rank > $currentRank && $earned->id !== $member->tier_id) {
            $member->forceFill([
                'tier_id' => $earned->id,
                'tier_assigned_at' => now(),
            ])->save();
        }
    }

    /**
     * Evaluate every active member of a community.
     *
     * @return int number of members whose tier changed
     */
    public function evaluateCommunity(Community $community): int
    {
        $changed = 0;

        $community->members()
            ->where('status', CommunityMemberStatus::Active->value)
            ->with('tier')
            ->each(function (CommunityMember $member) use (&$changed): void {
                $before = $member->tier_id;
                $this->evaluateMember($member);

                if ($member->fresh()->tier_id !== $before) {
                    $changed++;
                }
            });

        return $changed;
    }

    /**
     * The highest-rank non-manual tier whose rule the member satisfies, or null.
     */
    private function highestEarnedTier(CommunityMember $member): ?CommunityTier
    {
        $tiers = $member->community->tiers()
            ->where('assignment_rule', '!=', TierAssignmentRule::Manual->value)
            ->orderByDesc('rank')
            ->get();

        foreach ($tiers as $tier) {
            if ($this->satisfies($member, $tier)) {
                return $tier;
            }
        }

        return null;
    }

    private function satisfies(CommunityMember $member, CommunityTier $tier): bool
    {
        $threshold = (int) ($tier->threshold ?? 0);

        return match ($tier->assignment_rule) {
            TierAssignmentRule::XpThreshold => $this->memberXp($member) >= $threshold,
            TierAssignmentRule::Tenure => $member->joined_at !== null
                && $member->joined_at->diffInDays(now()) >= $threshold,
            TierAssignmentRule::EventsAttended => $this->eventsAttended($member) >= $threshold,
            TierAssignmentRule::Manual => false,
        };
    }

    /**
     * XP = sum of the member's append-only point_ledger entries (spec §0.6).
     */
    private function memberXp(CommunityMember $member): int
    {
        return (int) PointLedger::query()
            ->where('profile_id', $member->profile_id)
            ->sum('points');
    }

    /**
     * Count of the member's check-ins on events linked to THIS community
     * (events.community_id), per the §0.6 linkage decision.
     */
    private function eventsAttended(CommunityMember $member): int
    {
        return EventCheckin::query()
            ->where('profile_id', $member->profile_id)
            ->whereIn('event_id', Event::query()
                ->where('community_id', $member->community_id)
                ->select('id'))
            ->count();
    }
}
