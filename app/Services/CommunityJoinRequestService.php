<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\JoinRequestStatus;
use App\Enums\NotificationType;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CommunityJoinRequestService
{
    public function __construct(
        private readonly CommunityMemberService $memberService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * An attendee requests to join an invite_only community.
     *
     * Idempotent: if a pending request already exists it is returned as-is.
     * Open communities have no request flow (the caller should self-join);
     * we signal that with a 'community_is_open' DomainException.
     *
     * @throws DomainException 'community_is_open'
     */
    public function request(Community $community, Profile $profile): CommunityJoinRequest
    {
        if ($community->join_policy === JoinPolicy::Open) {
            throw new DomainException('community_is_open');
        }

        // Existing pending request → return it (idempotent re-request).
        $pending = $community->joinRequests()
            ->where('profile_id', $profile->id)
            ->where('status', JoinRequestStatus::Pending->value)
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $joinRequest = $community->joinRequests()->create([
            'profile_id' => $profile->id,
            'status' => JoinRequestStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->notifyManagersOfNewRequest($community, $profile);

        return $joinRequest;
    }

    /**
     * Pending requests for a community, requester profile eager-loaded.
     *
     * @return Collection<int, CommunityJoinRequest>
     */
    public function pending(Community $community): Collection
    {
        return $community->joinRequests()
            ->where('status', JoinRequestStatus::Pending->value)
            ->with(['profile.attendeeProfile', 'profile.communityProfile', 'profile.businessProfile'])
            ->orderBy('requested_at')
            ->get();
    }

    /**
     * Approve a pending request: create the member (default tier, active),
     * mark approved, notify the requester. Guards against double-approve.
     *
     * @throws DomainException 'already_decided'
     */
    public function approve(CommunityJoinRequest $joinRequest, Profile $decider): CommunityJoinRequest
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw new DomainException('already_decided');
        }

        return DB::transaction(function () use ($joinRequest, $decider): CommunityJoinRequest {
            $community = $joinRequest->community;

            // Create the membership exactly like a normal join (default tier, active).
            $this->memberService->addMember($community, $joinRequest->profile_id);

            $joinRequest->update([
                'status' => JoinRequestStatus::Approved->value,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ]);

            $this->notifyRequester(
                $joinRequest,
                NotificationType::CommunityJoinApproved,
                __('Request approved'),
                __('You are now a member of :community.', ['community' => $community->name]),
            );

            return $joinRequest->refresh();
        });
    }

    /**
     * Decline a pending request: mark declined, notify the requester.
     *
     * @throws DomainException 'already_decided'
     */
    public function decline(CommunityJoinRequest $joinRequest, Profile $decider): CommunityJoinRequest
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw new DomainException('already_decided');
        }

        $community = $joinRequest->community;

        $joinRequest->update([
            'status' => JoinRequestStatus::Declined->value,
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ]);

        $this->notifyRequester(
            $joinRequest,
            NotificationType::CommunityJoinDeclined,
            __('Request declined'),
            __('Your request to join :community was declined.', ['community' => $community->name]),
        );

        return $joinRequest->refresh();
    }

    /**
     * The viewer's current request status for a community, or null.
     */
    public function statusFor(Community $community, Profile $profile): ?JoinRequestStatus
    {
        $request = $community->joinRequests()
            ->where('profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->first();

        return $request?->status;
    }

    /**
     * Notify the owner + active can_manage members that a new request landed.
     */
    private function notifyManagersOfNewRequest(Community $community, Profile $requester): void
    {
        $managerIds = $community->members()
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->pluck('profile_id')
            ->all();

        $managerIds[] = $community->owner_profile_id;
        $managerIds = array_values(array_unique(array_filter($managerIds)));

        $requesterName = $requester->getExtendedProfile()?->name ?? $requester->name ?? __('Someone');
        $title = __('New join request');
        $body = __(':name asked to join :community.', [
            'name' => $requesterName,
            'community' => $community->name,
        ]);

        foreach (Profile::query()->whereIn('id', $managerIds)->get() as $manager) {
            $this->notifications->createNotification(
                recipient: $manager,
                type: NotificationType::CommunityJoinRequested,
                title: $title,
                body: $body,
                actor: $requester,
                targetId: $community->id,
                targetType: 'community',
            );
        }
    }

    private function notifyRequester(
        CommunityJoinRequest $joinRequest,
        NotificationType $type,
        string $title,
        string $body,
    ): void {
        $requester = $joinRequest->profile;

        if ($requester === null) {
            return;
        }

        $this->notifications->createNotification(
            recipient: $requester,
            type: $type,
            title: $title,
            body: $body,
            targetId: $joinRequest->community_id,
            targetType: 'community',
        );
    }
}
