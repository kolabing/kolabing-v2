<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\MissionTrigger;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckinService
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly TierAssignmentService $tierAssignmentService,
        private readonly CommunityPointsService $communityPointsService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * Generate a unique QR check-in token for an event.
     */
    public function generateCheckinToken(Event $event): string
    {
        $token = Str::random(64);
        $event->update([
            'checkin_token' => $token,
            'is_active' => true,
        ]);

        return $token;
    }

    /**
     * Check in an attendee using a QR token.
     */
    public function checkin(Profile $profile, string $token): EventCheckin
    {
        $event = Event::query()->where('checkin_token', $token)->first();

        if (! $event) {
            throw new \InvalidArgumentException('Invalid check-in token.');
        }

        if (! $event->is_active) {
            throw new \LogicException('This event is not currently accepting check-ins.');
        }

        $existing = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->exists();

        if ($existing) {
            throw new \LogicException('You have already checked in to this event.');
        }

        $checkin = EventCheckin::query()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'checked_in_at' => now(),
        ]);

        // Increment total_events_attended on attendee profile
        if ($profile->isAttendee() && $profile->attendeeProfile) {
            $profile->attendeeProfile->increment('total_events_attended');
            $profile->attendeeProfile->refresh();
            $this->badgeService->checkAndAwardBadges($profile);
        }

        // Per-community POINTS earn (+ mirrored global XP) when the event is
        // community-linked and the attendee is an active member. Never breaks
        // the check-in itself.
        try {
            $this->communityPointsService->awardEventCheckin($event, $profile->id, $checkin);
        } catch (\Throwable $e) {
            Log::warning('Community points award on check-in failed', [
                'profile_id' => $profile->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->evaluateCommunityTiers($profile);

        // Missions (guarded): the check-in itself progresses the attendee's
        // event_checkin missions, and — when the event is community-linked and
        // the attendee is an active member — the community owner's member_checkin
        // missions. Mirrors the community-points scoping. Never breaks check-in.
        $this->recordCheckinMissions($event, $profile, $checkin);

        return $checkin->load(['event', 'profile']);
    }

    /**
     * Fire mission progress for a check-in: event_checkin for the attendee, and
     * member_checkin for the community owner when the event is community-linked
     * and the attendee is an active member of that community. Fully guarded.
     */
    private function recordCheckinMissions(Event $event, Profile $profile, EventCheckin $checkin): void
    {
        try {
            $this->missionService->record(
                $profile,
                MissionTrigger::EventCheckin,
                1,
                ['reference_id' => $checkin->id],
            );
        } catch (\Throwable $e) {
            Log::warning('Mission record failed (event_checkin)', [
                'profile_id' => $profile->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($event->community_id === null) {
            return;
        }

        try {
            $community = Community::query()->find($event->community_id);

            if ($community === null) {
                return;
            }

            // Scope member_checkin to genuine member attendance, matching the
            // community-points rule (an owner only earns for their own members).
            $isMember = CommunityMember::query()
                ->where('community_id', $community->id)
                ->where('profile_id', $profile->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->exists();

            if (! $isMember) {
                return;
            }

            $owner = $community->owner;

            if ($owner === null) {
                return;
            }

            $this->missionService->record(
                $owner,
                MissionTrigger::MemberCheckin,
                1,
                ['reference_id' => $checkin->id],
            );
        } catch (\Throwable $e) {
            Log::warning('Mission record failed (member_checkin)', [
                'profile_id' => $profile->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-evaluate the member's tier in every community they belong to, so a
     * check-in promotes immediately (events_attended / xp rules) without
     * waiting for the nightly app:evaluate-community-tiers job. A failure here
     * must never break the check-in itself.
     */
    private function evaluateCommunityTiers(Profile $profile): void
    {
        try {
            CommunityMember::query()
                ->where('profile_id', $profile->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->with(['community', 'tier'])
                ->each(fn (CommunityMember $member) => $this->tierAssignmentService->evaluateMember($member));
        } catch (\Throwable $e) {
            Log::warning('Community tier evaluation on check-in failed', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * List check-ins for an event.
     */
    public function getCheckins(Event $event, int $perPage = 10): LengthAwarePaginator
    {
        return EventCheckin::query()
            ->where('event_id', $event->id)
            ->with(['profile'])
            ->orderByDesc('checked_in_at')
            ->paginate($perPage);
    }
}
