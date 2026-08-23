<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\EventSignupStatus;
use App\Enums\MissionTrigger;
use App\Events\AttendeeCheckedIn;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSignup;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckinService
{
    /** How long a door stays open at minimum, measured from the host opening it. */
    private const MINIMUM_DOOR_HOURS = 4;

    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly TierAssignmentService $tierAssignmentService,
        private readonly CommunityPointsService $communityPointsService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * Generate a unique QR check-in token for an event.
     */
    /**
     * Open the door, reusing the existing code when it is still valid.
     *
     * Idempotency matters because there are two clients. A host who opens the door
     * on a laptop and then opens it again on a phone must not invalidate the QR that
     * is still on the laptop screen — people would be standing in front of a dead
     * code with nothing to tell them. Rotating is therefore explicit: it is how a
     * host retires a code they think has leaked.
     */
    public function openDoor(Event $event, bool $rotate = false): string
    {
        $stillValid = $event->checkin_token !== null
            && $event->is_active
            && ($event->checkin_token_expires_at === null || $event->checkin_token_expires_at->isFuture());

        if ($stillValid && ! $rotate) {
            // Reopening extends the window without changing what is on screen.
            $event->update(['checkin_token_expires_at' => $this->checkinWindowEndsAt($event)]);

            return (string) $event->checkin_token;
        }

        return $this->generateCheckinToken($event);
    }

    /**
     * Mint a fresh token, code and window. Retires whatever came before it, so
     * prefer openDoor() unless you mean to invalidate the old code.
     */
    public function generateCheckinToken(Event $event): string
    {
        $token = Str::random(64);

        $event->update([
            'checkin_token' => $token,
            'checkin_code' => $this->uniqueCheckinCode(),
            // Opening the door is deliberate (is_active), but closing it must not
            // depend on anyone remembering: the token dies with the event window.
            'checkin_token_expires_at' => $this->checkinWindowEndsAt($event),
            'is_active' => true,
        ]);

        return $token;
    }

    /**
     * When the door closes.
     *
     * Anchored on the moment the host opened it, because opening is a deliberate
     * act: pressing the button must always give you a door, even for an event whose
     * recorded date is wrong or which only ever had a date and no times. The event's
     * own end can only ever *extend* that window, never cut it short — the first
     * version of this shortened it, and a legacy date-only event slammed shut the
     * instant it was opened.
     *
     * It still expires, which is the point: a QR photographed at one event cannot
     * manufacture attendance weeks later. A host who suspects a leaked code re-opens
     * the door, which mints a fresh token and code.
     */
    private function checkinWindowEndsAt(Event $event): Carbon
    {
        $floor = now()->addHours(self::MINIMUM_DOOR_HOURS);

        $fromEvent = match (true) {
            $event->ends_at !== null => $event->ends_at->copy()->addHour(),
            $event->starts_at !== null => $event->starts_at->copy()->addHours(6),
            default => null,
        };

        return $fromEvent !== null && $fromEvent->isAfter($floor) ? $fromEvent : $floor;
    }

    /**
     * A short code someone can read off a screen and type. The alphabet drops the
     * characters people confuse out loud — O/0, I/1/L — because this gets shouted
     * across a room.
     */
    private function uniqueCheckinCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Event::query()->where('checkin_code', $code)->exists());

        return $code;
    }

    /**
     * Check in an attendee using a QR token.
     */
    public function checkin(Profile $profile, string $token): EventCheckin
    {
        // The QR carries the long token; the typed fallback carries the short code.
        // Both are the same permission, so both are accepted here.
        $event = Event::query()
            ->where(fn ($query) => $query
                ->where('checkin_token', $token)
                ->orWhere('checkin_code', strtoupper($token)))
            ->first();

        if (! $event) {
            throw new \InvalidArgumentException('Invalid check-in token.');
        }

        if (! $event->is_active) {
            throw new \LogicException('This event is not currently accepting check-ins.');
        }

        if ($event->checkin_token_expires_at !== null && $event->checkin_token_expires_at->isPast()) {
            throw new \LogicException('Check-in for this event has closed.');
        }

        return $this->record($event, $profile);
    }

    /**
     * Check the caller in to an event they are going to, with no token
     * (kolabing-app#144).
     *
     * A second door into the same room. The existing one needs another person
     * present: the attendee scans a code the organizer is displaying. If nobody
     * is holding a phone at the entrance — most small community events — the
     * whole challenge loop is unreachable, because
     * ChallengeCompletionService::initiate requires an `event_checkins` row for
     * BOTH attendees and no client can fake one.
     *
     * What is asked instead: an RSVP, and the right day.
     *
     * That is a **weaker claim** than proof of a code the organizer was
     * physically showing, and "this person was in the room" is the claim the
     * platform sells. Accepted deliberately: a loop nobody can start is worth
     * less than a slightly softer claim, and the QR door is still there for when
     * the claim has to be strong. If it needs tightening, the next step is a
     * per-event organizer switch — not removing this.
     *
     * @throws \LogicException not going, wrong day, or already present
     */
    public function selfCheckin(Event $event, Profile $profile): EventCheckin
    {
        if (! $event->is_active) {
            throw new \LogicException('This event is not currently accepting check-ins.');
        }

        $going = EventSignup::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->where('status', '!=', EventSignupStatus::Cancelled->value)
            ->exists();

        if (! $going) {
            throw new \LogicException('Say you are going to this event before checking in.');
        }

        if (! $this->isHappeningToday($event)) {
            throw new \LogicException('You can only check in on the day of the event.');
        }

        return $this->record($event, $profile);
    }

    /**
     * Whether [$event] is on today, in the app timezone.
     *
     * A whole day rather than the exact starts_at..ends_at window, on purpose:
     * people arrive early and check in late, and a check-in refused because the
     * event starts in nine minutes reads as broken. The day is also the
     * resolution the rest of the app already uses — discover filters on
     * DATE(COALESCE(starts_at, event_date)) — so anything visible as "today" can
     * be checked in to.
     */
    private function isHappeningToday(Event $event): bool
    {
        $effective = $event->starts_at ?? $event->event_date;

        if ($effective === null) {
            return false;
        }

        return Carbon::parse($effective)->isSameDay(Carbon::now());
    }

    /**
     * Record someone as present, with every consequence that carries.
     *
     * Extracted because there are two doors into the same room and they have to
     * agree on what happens once someone is through it: in {@see checkin()} the
     * attendee scans a code the host is displaying, in {@see selfCheckin()} they
     * pick an event they already said they were going to. Different proof —
     * identical outcome: one `event_checkins` row, arrivals broadcast,
     * `total_events_attended` incremented, badges re-checked, community points
     * awarded, tiers re-evaluated, missions progressed.
     *
     * @throws \LogicException already present
     */
    public function record(Event $event, Profile $profile): EventCheckin
    {
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

        /*
         * The door is watched from more than one screen — a laptop at the entrance
         * and the host's phone, web and mobile. Polling makes them disagree for a
         * few seconds each time; broadcasting makes the count move on all of them at
         * once. Clients keep polling as a fallback, so this is an improvement rather
         * than a dependency.
         */
        broadcast(new AttendeeCheckedIn($checkin->fresh(['profile'])));

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
        $this->missionService->recordSafely(
            $profile,
            MissionTrigger::EventCheckin,
            1,
            ['reference_id' => $checkin->id],
        );

        if ($event->community_id === null) {
            return;
        }

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

        $this->missionService->recordSafely(
            $owner,
            MissionTrigger::MemberCheckin,
            1,
            ['reference_id' => $checkin->id],
        );
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
