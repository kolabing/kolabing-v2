<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\MissionTrigger;
use App\Models\Community;
use App\Models\CommunityJoinQuestion;
use App\Models\CommunityMember;
use App\Models\Profile;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommunityMemberService
{
    public function __construct(
        private readonly MissionService $missionService,
        private readonly CommunityRosterQuery $rosterQuery,
    ) {}

    /**
     * A person self-joins an open community. Blocked for invite_only.
     * Idempotent on the (community, profile) unique constraint.
     *
     * @throws DomainException 'invite_only'
     */
    public function join(Community $community, Profile $profile): CommunityMember
    {
        if ($community->join_policy !== JoinPolicy::Open) {
            throw new DomainException('invite_only');
        }

        // Questions are the leader's OPTIONAL choice: add none and joining stays
        // one tap through here; add one and joining goes through the
        // application. Letting /join through anyway would make that choice do
        // nothing — an open community could ask five required questions and
        // still take one-tap members.
        //
        // Costs nothing today: no community has questions until a leader
        // creates one, and creating one is itself a deliberate act.
        $hasQuestions = CommunityJoinQuestion::query()
            ->where('community_id', $community->id)
            ->where('is_active', true)
            ->exists();

        if ($hasQuestions) {
            throw new DomainException('join_requires_application');
        }

        $member = $this->upsertMember($community, $profile->id);

        // Missions: the joiner progresses community_joined, but only on a fresh
        // join (idempotent — re-joining an existing membership must not re-fire).
        // Audience scoping limits this to the attendee's missions. Guarded.
        if ($member->wasRecentlyCreated) {
            $this->missionService->recordSafely(
                $profile,
                MissionTrigger::CommunityJoined,
                1,
                ['reference_id' => $community->id],
            );
        }

        return $member;
    }

    /**
     * Leader / can_manage path: add a member regardless of join policy.
     * Optional $tierId sets the initial tier (defaults to the community default tier).
     */
    public function addMember(Community $community, string $profileId, ?string $tierId = null): CommunityMember
    {
        return $this->upsertMember($community, $profileId, $tierId);
    }

    /**
     * Set tier_id (manual promote), can_manage, or status on a membership.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMember(CommunityMember $member, array $data): CommunityMember
    {
        if (array_key_exists('tier_id', $data) && $data['tier_id'] !== $member->tier_id) {
            $member->tier_id = $data['tier_id'];
            $member->tier_assigned_at = now();
        }

        if (array_key_exists('can_manage', $data)) {
            $member->can_manage = (bool) $data['can_manage'];
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $member->status = $data['status'];
        }

        $member->save();

        return $member->refresh();
    }

    /**
     * Apply one change set to many memberships. Every id is verified to belong
     * to $community first, so a caller can never write across communities.
     *
     * @param  array<int, string>  $memberIds
     * @param  array<string, mixed>  $data
     * @return array{updated: int, skipped: int}
     */
    public function bulkUpdate(Community $community, array $memberIds, array $data): array
    {
        $memberIds = array_values(array_unique($memberIds));

        $members = $community->members()->whereIn('id', $memberIds)->get();

        DB::transaction(function () use ($members, $data): void {
            foreach ($members as $member) {
                $this->updateMember($member, $data);
            }
        });

        return [
            'updated' => $members->count(),
            'skipped' => count($memberIds) - $members->count(),
        ];
    }

    /**
     * Soft-remove a member (status -> removed; row kept for history).
     */
    public function remove(CommunityMember $member): void
    {
        $member->update(['status' => CommunityMemberStatus::Removed->value]);
    }

    /**
     * Paginated roster with nested tier + profile and per-member engagement
     * metrics, filtered and sorted by the caller. See CommunityRosterQuery.
     *
     * @param  array<string, mixed>  $filters
     */
    public function roster(Community $community, int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        return $this->rosterQuery->paginate($community, $filters, $perPage);
    }

    private function upsertMember(Community $community, string $profileId, ?string $tierId = null): CommunityMember
    {
        $existing = $community->members()->where('profile_id', $profileId)->first();

        if ($existing) {
            return $existing;
        }

        $resolvedTierId = $tierId ?? $community->defaultTier?->id;

        return $community->members()->create([
            'profile_id' => $profileId,
            'tier_id' => $resolvedTierId,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => now(),
            'tier_assigned_at' => $resolvedTierId !== null ? now() : null,
        ]);
    }
}
