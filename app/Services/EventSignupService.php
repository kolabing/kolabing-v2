<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Enums\NotificationType;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Upcoming-event sign-ups with capacity + waitlist (NF-CHAT Phase 3).
 *
 * Binary "I'm going". Over capacity → waitlisted with a position; on a
 * leave/cancel the head of the waitlist auto-promotes to going and is notified.
 * Access to the event chat is DERIVED from a `going` row (see ChatService).
 */
class EventSignupService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Join an upcoming event (or land on the waitlist if full).
     *
     * @throws DomainException 'event_not_upcoming' | 'event_not_signup_enabled'
     *                         | 'not_a_member' | 'tier_not_permitted'
     */
    public function signup(Event $event, Profile $profile): EventSignup
    {
        if (! $event->isUpcoming()) {
            throw new DomainException('event_not_upcoming');
        }

        if ($event->community_id === null) {
            throw new DomainException('event_not_signup_enabled');
        }

        $this->assertEligible($event, $profile);

        return DB::transaction(function () use ($event, $profile): EventSignup {
            // Serialize all sign-ups for this event by locking the EVENT ROW (a
            // single-row `SELECT ... FOR UPDATE`, legal on Postgres). We must NOT
            // lock the count() below: Postgres forbids `FOR UPDATE` with an
            // aggregate ("FOR UPDATE is not allowed with aggregate functions"),
            // which 500'd every sign-up in prod while SQLite tests passed.
            Event::query()->whereKey($event->id)->lockForUpdate()->first();

            $existing = EventSignup::query()
                ->where('event_id', $event->id)
                ->where('profile_id', $profile->id)
                ->first(); // event-row lock already serializes us

            if ($existing !== null && $existing->status !== EventSignupStatus::Cancelled) {
                return $existing; // already going or waitlisted — idempotent
            }

            $goingCount = EventSignup::query()
                ->where('event_id', $event->id)
                ->where('status', EventSignupStatus::Going->value)
                ->count(); // no lockForUpdate(): aggregate + FOR UPDATE is illegal on Postgres

            $hasRoom = $event->capacity === null || $goingCount < $event->capacity;

            $status = $hasRoom ? EventSignupStatus::Going : EventSignupStatus::Waitlisted;
            $position = $hasRoom ? null : $this->nextWaitlistPosition($event);

            $attributes = [
                'status' => $status->value,
                'waitlist_position' => $position,
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return $existing->refresh();
            }

            return EventSignup::query()->create([
                'event_id' => $event->id,
                'profile_id' => $profile->id,
                ...$attributes,
            ]);
        });
    }

    /**
     * Leave/cancel a sign-up. If a `going` seat is freed, auto-promote the head
     * of the waitlist and notify them.
     */
    public function cancel(Event $event, Profile $profile): void
    {
        DB::transaction(function () use ($event, $profile): void {
            $signup = EventSignup::query()
                ->where('event_id', $event->id)
                ->where('profile_id', $profile->id)
                ->lockForUpdate()
                ->first();

            if ($signup === null || $signup->status === EventSignupStatus::Cancelled) {
                return;
            }

            $wasGoing = $signup->status === EventSignupStatus::Going;
            $signup->update([
                'status' => EventSignupStatus::Cancelled->value,
                'waitlist_position' => null,
            ]);

            if ($wasGoing) {
                $this->promoteNextWaitlisted($event);
            } else {
                $this->resequenceWaitlist($event);
            }
        });
    }

    /**
     * @return int going count
     */
    public function goingCount(Event $event): int
    {
        return EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Going->value)
            ->count();
    }

    public function waitlistCount(Event $event): int
    {
        return EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Waitlisted->value)
            ->count();
    }

    public function signupFor(Event $event, Profile $profile): ?EventSignup
    {
        return EventSignup::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->first();
    }

    /**
     * Attach per-viewer signup counts and access booleans to a list of events in
     * bulk so API resources do not run count/member queries for each row.
     *
     * @param  iterable<int, Event>  $events
     */
    public function hydrateSummaries(iterable $events, ?Profile $viewer): void
    {
        $events = collect($events)->values();
        if ($events->isEmpty()) {
            return;
        }

        $eventIds = $events->pluck('id')->filter()->values();

        $counts = EventSignup::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', [
                EventSignupStatus::Going->value,
                EventSignupStatus::Waitlisted->value,
            ])
            ->selectRaw('event_id, status, COUNT(*) as aggregate')
            ->groupBy('event_id', 'status')
            ->get()
            ->groupBy('event_id');

        $viewerSignups = $viewer === null
            ? collect()
            : EventSignup::query()
                ->whereIn('event_id', $eventIds)
                ->where('profile_id', $viewer->id)
                ->get()
                ->keyBy('event_id');

        $communityIds = $events->pluck('community_id')->filter()->unique()->values();
        $ownedCommunityIds = collect();
        $memberships = collect();

        if ($viewer !== null && $communityIds->isNotEmpty()) {
            $ownedCommunityIds = Community::query()
                ->whereIn('id', $communityIds)
                ->where('owner_profile_id', $viewer->id)
                ->pluck('id');

            $memberships = CommunityMember::query()
                ->whereIn('community_id', $communityIds)
                ->where('profile_id', $viewer->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->get()
                ->keyBy('community_id');
        }

        foreach ($events as $event) {
            $eventCounts = $counts->get($event->id, collect());
            $event->setAttribute(
                'going_count',
                (int) ($eventCounts->firstWhere('status', EventSignupStatus::Going->value)?->aggregate ?? 0)
            );
            $event->setAttribute(
                'waitlist_count',
                (int) ($eventCounts->firstWhere('status', EventSignupStatus::Waitlisted->value)?->aggregate ?? 0)
            );

            $signup = $viewerSignups->get($event->id);
            $event->setAttribute('viewer_signup_status', $signup?->status?->value);
            $event->setAttribute('viewer_signup_waitlist_position', $signup?->waitlist_position);

            $event->setAttribute(
                'viewer_can_access',
                $this->canAccessFromLoadedState($event, $viewer, $ownedCommunityIds, $memberships)
            );
        }
    }

    /**
     * Whether a profile currently holds a `going` seat (drives chat access).
     */
    public function isGoing(Event $event, Profile $profile): bool
    {
        return EventSignup::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->where('status', EventSignupStatus::Going->value)
            ->exists();
    }

    /**
     * @param  Collection<int, string>  $ownedCommunityIds
     * @param  Collection<string, CommunityMember>  $memberships
     */
    private function canAccessFromLoadedState(
        Event $event,
        ?Profile $viewer,
        Collection $ownedCommunityIds,
        Collection $memberships
    ): bool {
        if ($event->community_id === null) {
            return true;
        }

        $visibility = $event->visibility instanceof EventVisibility
            ? $event->visibility
            : EventVisibility::tryFrom((string) $event->visibility);

        if ($visibility === EventVisibility::Public) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        if ($ownedCommunityIds->contains($event->community_id)) {
            return true;
        }

        $member = $memberships->get($event->community_id);
        if ($member === null) {
            return false;
        }

        $gate = $event->tier_gate ?? [];

        return ! (is_array($gate) && $gate !== [] && ! in_array($member->tier_id, $gate, true));
    }

    private function assertEligible(Event $event, Profile $profile): void
    {
        // Public events are open to everyone — no community membership required.
        if ($event->visibility === EventVisibility::Public) {
            return;
        }

        // The community owner (leader) is always eligible.
        if (Community::query()->whereKey($event->community_id)->where('owner_profile_id', $profile->id)->exists()) {
            return;
        }

        $member = CommunityMember::query()
            ->where('community_id', $event->community_id)
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->first();

        if ($member === null) {
            throw new DomainException('not_a_member');
        }

        // Optional tier gate: tier_gate is a list of allowed tier ids.
        $gate = $event->tier_gate ?? [];
        if (is_array($gate) && $gate !== [] && ! in_array($member->tier_id, $gate, true)) {
            throw new DomainException('tier_not_permitted');
        }
    }

    /**
     * Whether a viewer may open / sign up for this event — the boolean form of
     * {@see assertEligible}. Drives the per-event "locked" lock icon in the app
     * so a tier-gated event a member cannot join is not even openable.
     *
     * Non-community events (no community_id) are open to all. Otherwise: the
     * community owner always passes; a non-member or a member whose tier is not
     * in a non-empty tier_gate is locked out.
     */
    public function canAccess(Event $event, ?Profile $profile): bool
    {
        if ($event->community_id === null) {
            return true;
        }

        // Public events are accessible to everyone.
        if ($event->visibility === EventVisibility::Public) {
            return true;
        }

        if ($profile === null) {
            return false;
        }

        if (Community::query()->whereKey($event->community_id)->where('owner_profile_id', $profile->id)->exists()) {
            return true;
        }

        $member = CommunityMember::query()
            ->where('community_id', $event->community_id)
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->first();

        if ($member === null) {
            return false;
        }

        $gate = $event->tier_gate ?? [];

        return ! (is_array($gate) && $gate !== [] && ! in_array($member->tier_id, $gate, true));
    }

    private function nextWaitlistPosition(Event $event): int
    {
        $max = EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Waitlisted->value)
            ->max('waitlist_position');

        return (int) $max + 1;
    }

    private function promoteNextWaitlisted(Event $event): void
    {
        $next = EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Waitlisted->value)
            ->orderBy('waitlist_position')
            ->lockForUpdate()
            ->first();

        if ($next === null) {
            return;
        }

        $next->update([
            'status' => EventSignupStatus::Going->value,
            'waitlist_position' => null,
        ]);

        $this->resequenceWaitlist($event);

        $this->notificationService->createLocalizedNotification(
            recipient: $next->profile,
            type: NotificationType::WaitlistPromoted,
            titleKey: 'notifications.waitlist.promoted.title',
            bodyKey: 'notifications.waitlist.promoted.body',
            replace: ['event' => $event->name],
            targetId: $event->id,
            targetType: 'event',
        );
    }

    /**
     * Re-number remaining waitlist rows to 1..N (stable by current position).
     */
    private function resequenceWaitlist(Event $event): void
    {
        $waitlisted = EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Waitlisted->value)
            ->orderBy('waitlist_position')
            ->get();

        $position = 1;
        foreach ($waitlisted as $row) {
            if ($row->waitlist_position !== $position) {
                $row->update(['waitlist_position' => $position]);
            }
            $position++;
        }
    }
}
