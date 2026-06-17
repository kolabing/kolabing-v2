<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommunityMemberService
{
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

        return $this->upsertMember($community, $profile->id);
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
     * Soft-remove a member (status -> removed; row kept for history).
     */
    public function remove(CommunityMember $member): void
    {
        $member->update(['status' => CommunityMemberStatus::Removed->value]);
    }

    /**
     * Paginated roster with nested tier + profile for one-call rendering.
     */
    public function roster(Community $community, int $perPage = 25): LengthAwarePaginator
    {
        return $community->members()
            ->with([
                'tier',
                'profile.attendeeProfile',
                'profile.businessProfile',
                'profile.communityProfile',
            ])
            ->orderBy('created_at')
            ->paginate($perPage);
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
