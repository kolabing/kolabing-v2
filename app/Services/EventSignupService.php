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

        $this->notificationService->createNotification(
            recipient: $next->profile,
            type: NotificationType::WaitlistPromoted,
            title: __('You\'re in!'),
            body: __('A spot opened up for ":event" — you\'re now going.', ['event' => $event->name]),
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
