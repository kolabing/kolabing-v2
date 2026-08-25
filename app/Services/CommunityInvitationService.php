<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityInvitationStatus;
use App\Enums\CommunityMemberStatus;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\Profile;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pending email invitations to a community.
 *
 * Deliberately a separate resource from POST /communities/{id}/members: turning
 * that endpoint's 404 into a 201 would silently change the contract the mobile
 * client is written against.
 */
class CommunityInvitationService
{
    public function __construct(
        private readonly CommunityMemberService $memberService,
    ) {}

    /**
     * Invite one email. Idempotent: re-inviting the same address re-uses the
     * pending row and refreshes its window, mirroring
     * CommunityMemberService::upsertMember.
     *
     * @return array{status: string, invitation: CommunityInvitation|null}
     */
    public function invite(Community $community, string $email, ?string $tierId, ?Profile $invitedBy): array
    {
        $email = mb_strtolower(trim($email));

        if ($this->isActiveMember($community, $email)) {
            return ['status' => 'already_member', 'invitation' => null];
        }

        $ttl = (int) config('communities.invitation_ttl_days', 30);

        $invitation = CommunityInvitation::query()
            ->where('community_id', $community->id)
            ->where('email', $email)
            ->where('status', CommunityInvitationStatus::Pending->value)
            ->first();

        if ($invitation) {
            $invitation->update([
                'tier_id' => $tierId ?? $invitation->tier_id,
                'expires_at' => now()->addDays($ttl),
            ]);
        } else {
            $invitation = CommunityInvitation::query()->create([
                'community_id' => $community->id,
                'email' => $email,
                'tier_id' => $tierId,
                'token' => Str::random(64),
                'invited_by_profile_id' => $invitedBy?->id,
                'status' => CommunityInvitationStatus::Pending->value,
                'expires_at' => now()->addDays($ttl),
            ]);
        }

        $invitation = $invitation->fresh();
        $this->sendSafely($invitation);

        return ['status' => 'invited', 'invitation' => $invitation];
    }

    /**
     * Redeem an invitation. The token IS the authorization (the same model as
     * Community::inviteUrlWithToken), so the caller's email need not match —
     * whoever redeemed it is recorded in accepted_profile_id.
     *
     * @throws DomainException 'not_claimable'
     */
    public function accept(CommunityInvitation $invitation, Profile $profile): CommunityInvitation
    {
        if (! $invitation->isClaimable()) {
            throw new DomainException('not_claimable');
        }

        $invitation->loadMissing('community');

        if ($invitation->community === null) {
            throw new DomainException('not_claimable');
        }

        $this->memberService->addMember(
            $invitation->community,
            $profile->id,
            $invitation->tier_id,
        );

        $invitation->update([
            'status' => CommunityInvitationStatus::Accepted->value,
            'accepted_at' => now(),
            'accepted_profile_id' => $profile->id,
        ]);

        return $invitation->fresh();
    }

    public function revoke(CommunityInvitation $invitation): CommunityInvitation
    {
        $invitation->update(['status' => CommunityInvitationStatus::Revoked->value]);

        return $invitation->fresh();
    }

    public function resend(CommunityInvitation $invitation): CommunityInvitation
    {
        $invitation->update([
            'status' => CommunityInvitationStatus::Pending->value,
            'expires_at' => now()->addDays((int) config('communities.invitation_ttl_days', 30)),
        ]);

        $invitation = $invitation->fresh();
        $this->sendSafely($invitation);

        return $invitation;
    }

    /**
     * Claim every pending invitation addressed to a freshly-registered profile.
     *
     * Guarded: a failure here must never break signup — the same contract as
     * OnboardingService::autoJoinCommunities and the mission hooks.
     */
    public function claimForSafely(Profile $profile): void
    {
        try {
            CommunityInvitation::query()
                ->with('community')
                ->where('email', mb_strtolower($profile->email))
                ->where('status', CommunityInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->get()
                ->each(function (CommunityInvitation $invitation) use ($profile): void {
                    if ($invitation->community === null) {
                        return;
                    }

                    $this->accept($invitation, $profile);
                });
        } catch (Throwable $e) {
            Log::warning('Community invitation claim failed', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isActiveMember(Community $community, string $email): bool
    {
        $profileId = Profile::query()->where('email', $email)->value('id');

        if ($profileId === null) {
            return false;
        }

        return $community->members()
            ->where('profile_id', $profileId)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    /** Mail is queued; a mail failure must not lose the invitation row. */
    private function sendSafely(CommunityInvitation $invitation): void
    {
        try {
            Mail::to($invitation->email)->send(new CommunityInvitationMail($invitation));
        } catch (Throwable $e) {
            Log::warning('Community invitation mail failed', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
