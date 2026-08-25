<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityGoalEarnType;
use App\Enums\CommunityMemberStatus;
use App\Enums\CommunityPointSource;
use App\Enums\PointEventType;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityGoal;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\EventCheckin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The per-community POINTS economy. A member earns a community's points from:
 *  (a) event check-in, (b) community goal completion, (c) challenge verify.
 *
 * Points stay PER-COMMUNITY (community_points + community_point_ledger).
 * Earning community points ALSO awards GLOBAL XP via the existing
 * GamificationWalletService / xp_earn_rules — XP stays global.
 */
class CommunityPointsService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
        private readonly CommunityBadgeService $badgeService,
        private readonly TierAssignmentService $tierAssignmentService,
    ) {}

    /**
     * Current per-community points balance for a member (0 when none).
     */
    public function balance(Community $community, string $profileId): int
    {
        return (int) (CommunityPoints::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->value('points') ?? 0);
    }

    /**
     * Award `points` of a community's points to a member: writes the ledger,
     * bumps the denormalised balance, mirrors a global XP award, then runs the
     * community badge + tier evaluation. Idempotency for goal/check-in sources
     * is the caller's responsibility (see awardEventCheckin / completeGoals).
     */
    public function award(
        Community $community,
        string $profileId,
        int $points,
        CommunityPointSource $source,
        ?PointEventType $xpEvent = null,
        ?string $referenceId = null,
        ?string $description = null,
    ): CommunityPointLedger {
        $entry = DB::transaction(function () use ($community, $profileId, $points, $source, $referenceId, $description): CommunityPointLedger {
            $entry = CommunityPointLedger::query()->create([
                'community_id' => $community->id,
                'profile_id' => $profileId,
                'points' => $points,
                'source' => $source,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $balance = CommunityPoints::query()->firstOrCreate(
                ['community_id' => $community->id, 'profile_id' => $profileId],
                ['points' => 0],
            );
            $balance->increment('points', $points);

            return $entry;
        });

        // Mirror a GLOBAL XP award (kept global, separate balance).
        if ($xpEvent !== null && $points > 0) {
            try {
                $this->walletService->awardPoints($profileId, $xpEvent, $referenceId, $description);
            } catch (\Throwable $e) {
                Log::warning('Global XP mirror award failed', [
                    'profile_id' => $profileId,
                    'event' => $xpEvent->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->afterEarn($community, $profileId);

        return $entry;
    }

    /**
     * Earn hook for an event check-in. No-op when the event is not linked to a
     * community or the profile is not an active member. Idempotent per check-in
     * via the ledger reference_id.
     */
    public function awardEventCheckin(Event $event, string $profileId, EventCheckin $checkin): void
    {
        $community = $event->community_id !== null ? Community::query()->find($event->community_id) : null;

        if ($community === null || ! $this->isActiveMember($community, $profileId)) {
            return;
        }

        $already = CommunityPointLedger::query()
            ->where('source', CommunityPointSource::EventCheckIn->value)
            ->where('reference_id', $checkin->id)
            ->exists();

        if ($already) {
            return;
        }

        $this->award(
            $community,
            $profileId,
            PointEventType::EventCheckin->defaultPoints(),
            CommunityPointSource::EventCheckIn,
            PointEventType::EventCheckin,
            $checkin->id,
            'Event check-in',
        );

        // The check-in may also satisfy event_check_ins / days_in_community goals.
        $this->completeGoals($community, $profileId);
    }

    /**
     * Earn hook for a verified challenge completion. No-op when the challenge's
     * event is not community-linked or the challenger is not an active member.
     */
    public function awardChallengeVerified(ChallengeCompletion $completion): void
    {
        $event = $completion->event;
        if ($event === null || $event->community_id === null) {
            return;
        }

        $community = Community::query()->find($event->community_id);

        if ($community === null) {
            return;
        }

        // The community points awarded mirror the challenge's own point value.
        $points = (int) ($completion->points_earned ?: $completion->challenge?->points ?? 0);
        if ($points <= 0) {
            $points = PointEventType::CommunityChallengeVerified->defaultPoints();
        }

        // Both participants earn (kolabing-app#140) — a challenge is something
        // the two of them did. Each is gated on their own membership, so a
        // follower playing alongside a member does not get community points.
        $participants = array_unique(array_filter([
            $completion->challenger_profile_id,
            $completion->verifier_profile_id,
        ]));

        foreach ($participants as $profileId) {
            if (! $this->isActiveMember($community, $profileId)) {
                continue;
            }

            // Dedupe PER PROFILE: one completion now legitimately produces two
            // ledger rows, so the reference id alone can no longer identify a
            // duplicate.
            $already = CommunityPointLedger::query()
                ->where('source', CommunityPointSource::ChallengeVerified->value)
                ->where('reference_id', $completion->id)
                ->where('profile_id', $profileId)
                ->exists();

            if ($already) {
                continue;
            }

            $this->award(
                $community,
                $profileId,
                $points,
                CommunityPointSource::ChallengeVerified,
                PointEventType::CommunityChallengeVerified,
                $completion->id,
                'Challenge verified',
            );

            $this->completeGoals($community, $profileId);
        }
    }

    /**
     * Evaluate every active community goal for a member and award its
     * reward_points once when the target is reached. Idempotent: a goal pays
     * out at most once per member (ledger reference_id = goal id).
     */
    public function completeGoals(Community $community, string $profileId): void
    {
        if (! $this->isActiveMember($community, $profileId)) {
            return;
        }

        $goals = $community->goals()->where('is_active', true)->get();

        if ($goals->isEmpty()) {
            return;
        }

        // Bulk-resolve the two per-goal reads that were previously an N+1: which
        // goals are already paid, and each goal's progress.
        $paidGoalIds = CommunityPointLedger::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->where('source', CommunityPointSource::GoalCompleted->value)
            ->whereIn('reference_id', $goals->pluck('id'))
            ->pluck('reference_id')
            ->all();

        $progressByGoal = $this->goalProgressForMany($community, $profileId, $goals);

        foreach ($goals as $goal) {
            if (in_array($goal->id, $paidGoalIds, true)) {
                continue;
            }

            if (($progressByGoal[$goal->id] ?? 0) >= $goal->target) {
                $this->award(
                    $community,
                    $profileId,
                    $goal->reward_points,
                    CommunityPointSource::GoalCompleted,
                    PointEventType::CommunityGoalCompleted,
                    $goal->id,
                    'Goal: '.$goal->title,
                );
            }
        }
    }

    /**
     * A member's progress count toward a goal in its own unit.
     */
    public function goalProgress(Community $community, string $profileId, CommunityGoal $goal): int
    {
        return match ($goal->earn_type) {
            CommunityGoalEarnType::EventCheckIns => $this->communityCheckinCount($community, $profileId),
            CommunityGoalEarnType::DaysInCommunity => $this->daysInCommunity($community, $profileId),
            CommunityGoalEarnType::Challenge => $this->challengeVerifiedCount($community, $profileId, $goal->challenge_id),
        };
    }

    /**
     * Progress for many goals of one community/member, keyed by goal id. Each
     * earn-type is resolved with a bounded number of queries regardless of how
     * many goals use it (no per-goal N+1): the community-wide check-in count and
     * days-in-community are computed at most once each, and Challenge goals share
     * a single grouped count over their challenge ids. Values match goalProgress().
     *
     * @param  Collection<int, CommunityGoal>  $goals
     * @return array<string, int>
     */
    public function goalProgressForMany(Community $community, string $profileId, Collection $goals): array
    {
        if ($goals->isEmpty()) {
            return [];
        }

        $checkinCount = $goals->contains(fn (CommunityGoal $g): bool => $g->earn_type === CommunityGoalEarnType::EventCheckIns)
            ? $this->communityCheckinCount($community, $profileId)
            : 0;

        $days = $goals->contains(fn (CommunityGoal $g): bool => $g->earn_type === CommunityGoalEarnType::DaysInCommunity)
            ? $this->daysInCommunity($community, $profileId)
            : 0;

        $challengeGoals = $goals->filter(fn (CommunityGoal $g): bool => $g->earn_type === CommunityGoalEarnType::Challenge);
        $challengeIds = $challengeGoals->pluck('challenge_id')->filter()->unique()->values();

        $challengeCounts = $challengeIds->isEmpty()
            ? collect()
            : ChallengeCompletion::query()
                ->where('challenger_profile_id', $profileId)
                ->where('status', \App\Enums\ChallengeCompletionStatus::Verified->value)
                ->whereIn('event_id', Event::query()->where('community_id', $community->id)->select('id'))
                ->whereIn('challenge_id', $challengeIds)
                ->selectRaw('challenge_id, count(*) as aggregate')
                ->groupBy('challenge_id')
                ->pluck('aggregate', 'challenge_id');

        // A Challenge goal with a null challenge_id counts ALL verified completions
        // in the community (matching goalProgress → challengeVerifiedCount(null)).
        $allVerifiedCount = $challengeGoals->contains(fn (CommunityGoal $g): bool => $g->challenge_id === null)
            ? $this->challengeVerifiedCount($community, $profileId, null)
            : 0;

        return $goals->mapWithKeys(function (CommunityGoal $goal) use ($checkinCount, $days, $challengeCounts, $allVerifiedCount): array {
            $progress = match ($goal->earn_type) {
                CommunityGoalEarnType::EventCheckIns => $checkinCount,
                CommunityGoalEarnType::DaysInCommunity => $days,
                CommunityGoalEarnType::Challenge => $goal->challenge_id === null
                    ? $allVerifiedCount
                    : (int) ($challengeCounts[$goal->challenge_id] ?? 0),
            };

            return [(string) $goal->id => $progress];
        })->all();
    }

    private function afterEarn(Community $community, string $profileId): void
    {
        try {
            $this->badgeService->evaluate($community, $profileId);
        } catch (\Throwable $e) {
            Log::warning('Community badge evaluation failed', ['error' => $e->getMessage()]);
        }

        try {
            $member = CommunityMember::query()
                ->where('community_id', $community->id)
                ->where('profile_id', $profileId)
                ->with(['community', 'tier'])
                ->first();
            if ($member !== null) {
                $this->tierAssignmentService->evaluateMember($member);
            }
        } catch (\Throwable $e) {
            Log::warning('Community tier evaluation on earn failed', ['error' => $e->getMessage()]);
        }
    }

    private function isActiveMember(Community $community, string $profileId): bool
    {
        return CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    private function communityCheckinCount(Community $community, string $profileId): int
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

    private function challengeVerifiedCount(Community $community, string $profileId, ?string $challengeId): int
    {
        $query = ChallengeCompletion::query()
            ->where('challenger_profile_id', $profileId)
            ->where('status', \App\Enums\ChallengeCompletionStatus::Verified->value)
            ->whereIn('event_id', Event::query()
                ->where('community_id', $community->id)
                ->select('id'));

        if ($challengeId !== null) {
            $query->where('challenge_id', $challengeId);
        }

        return $query->count();
    }
}
