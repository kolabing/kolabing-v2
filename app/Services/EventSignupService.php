<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Enums\NotificationType;
use App\Models\Community;
use App\Models\CommunityFollower;
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
        private readonly TicketService $ticketService,
        private readonly NotificationReminderService $notificationReminderService,
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

        /*
         * What makes an event joinable is that it is *public or belongs to a
         * community* — not that it belongs to a community.
         *
         * This used to require `community_id`, which quietly made the main case
         * impossible: a confirmed Kolab's happening is hosted by the two partners,
         * and `events.community_id` points at the NF-6 `communities` table, which a
         * community *profile* may well have no row in. So every Kolab happening
         * answered "sign-ups are not enabled here" no matter how public it was.
         *
         * `visibility` is the honest discriminator, and it is safe: the column
         * defaults to `members`, so portfolio and past-event rows — the reason the
         * original guard existed — are still not joinable by anyone.
         */
        if ($event->community_id === null && $event->visibility !== EventVisibility::Public) {
            throw new DomainException('event_not_signup_enabled');
        }

        $this->assertEligible($event, $profile);

        $signup = DB::transaction(function () use ($event, $profile): EventSignup {
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
                // Idempotent — already going or waitlisted. Ticketing still runs
                // below, which is what backfills rows that predate tickets.
                return $existing;
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

        /*
         * A seat becomes a ticket, and the ticket is emailed — but only after the
         * transaction has committed. On a `sync` queue the mail would otherwise be
         * built and sent inside the transaction, so a later rollback would leave
         * someone holding a ticket for a sign-up that does not exist.
         *
         * A waitlisted row gets nothing: it holds no seat, and a ticket that might
         * not be honoured is worse than no ticket. It is issued on promotion instead
         * ({@see promoteNextWaitlisted()}).
         */
        if ($signup->status === EventSignupStatus::Going && $signup->ticket_code === null) {
            $signup = $this->ticketService->issueAndSend($signup);
        }

        // Reminders are scheduled after commit for the same reason the ticket is:
        // a rolled-back sign-up must not leave a chain behind. Waitlisted rows get
        // nothing — syncEventReminders() checks the status itself.
        $signup->setRelation('event', $event);
        $this->notificationReminderService->syncEventReminders($signup);

        return $signup;
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

        $this->notificationReminderService->cancelEventReminders($event->id, $profile->id);
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
        $followedCommunityIds = collect();

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

            // One query for the whole page, not one per row: `followers`
            // visibility needs this and the list must not become an N+1 to get
            // it (kolabing-app#157).
            $followedCommunityIds = CommunityFollower::query()
                ->whereIn('community_id', $communityIds)
                ->where('profile_id', $viewer->id)
                ->pluck('community_id');
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
                $this->canAccessFromLoadedState(
                    $event,
                    $viewer,
                    $ownedCommunityIds,
                    $memberships,
                    $followedCommunityIds
                )
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
     * The list-view form: same rule, state already loaded.
     *
     * It exists so hydrating a page of events does not fire a query per row —
     * and it answers by handing its loaded state to {@see audienceRefusal}, so
     * it cannot drift from the detail view's answer.
     *
     * @param  Collection<int, string>  $ownedCommunityIds
     * @param  Collection<string, CommunityMember>  $memberships
     * @param  Collection<int, string>  $followedCommunityIds
     */
    private function canAccessFromLoadedState(
        Event $event,
        ?Profile $viewer,
        Collection $ownedCommunityIds,
        Collection $memberships,
        Collection $followedCommunityIds
    ): bool {
        if ($event->community_id === null) {
            return true;
        }

        if ($viewer === null) {
            return $event->visibility === EventVisibility::Public;
        }

        return $this->audienceRefusal($event, [
            'owner' => $ownedCommunityIds->contains($event->community_id),
            'member' => $memberships->get($event->community_id),
            'follows' => $followedCommunityIds->contains($event->community_id),
        ]) === null;
    }

    /**
     * Who may open and join this event — the ONE place the four audiences are
     * decided (kolabing-app#157).
     *
     *   public          anyone
     *   followers       anyone who follows the community
     *   members         an active member
     *   active_members  a member who has attended within 90 days
     *   (+ tier_gate)   a member whose tier is in the list, on top of the above
     *
     * Returns null when allowed, or the reason code the caller turns into an
     * exception or a boolean.
     *
     * Extracted because this rule had three implementations —
     * `assertEligible`, `canAccess`, and the preloaded
     * `canAccessFromLoadedState` — and three copies of an access rule is how a
     * list view and a detail view end up disagreeing about who can see what.
     * The preloaded version still exists (it must, for the list N+1) but now
     * answers by handing its loaded state to the same logic.
     *
     * @param  array{owner: bool, member: ?CommunityMember, follows: bool}  $state
     */
    private function audienceRefusal(Event $event, array $state): ?string
    {
        $visibility = $event->visibility instanceof EventVisibility
            ? $event->visibility
            : EventVisibility::tryFrom((string) $event->visibility);

        if ($visibility === EventVisibility::Public) {
            return null;
        }

        // The leader is never locked out of their own community's event.
        if ($state['owner']) {
            return null;
        }

        if ($visibility === EventVisibility::Followers) {
            // A member always follows (kolabing-app#146), so membership implies
            // this. Checking both anyway: a membership that predates the
            // backfill would otherwise be refused from its own community's most
            // open event, which would be absurd.
            return ($state['follows'] || $state['member'] !== null) ? null : 'not_a_follower';
        }

        $member = $state['member'];

        if ($member === null) {
            return 'not_a_member';
        }

        if ($visibility === EventVisibility::ActiveMembers && ! $member->isActiveMember()) {
            return 'not_an_active_member';
        }

        $gate = $event->tier_gate ?? [];
        if (is_array($gate) && $gate !== [] && ! in_array($member->tier_id, $gate, true)) {
            return 'tier_not_permitted';
        }

        return null;
    }

    private function assertEligible(Event $event, Profile $profile): void
    {
        if ($event->community_id === null) {
            return;
        }

        $refusal = $this->audienceRefusal($event, [
            'owner' => Community::query()
                ->whereKey($event->community_id)
                ->where('owner_profile_id', $profile->id)
                ->exists(),
            'member' => CommunityMember::query()
                ->where('community_id', $event->community_id)
                ->where('profile_id', $profile->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->first(),
            'follows' => CommunityFollower::query()
                ->where('community_id', $event->community_id)
                ->where('profile_id', $profile->id)
                ->exists(),
        ]);

        if ($refusal !== null) {
            throw new DomainException($refusal);
        }
    }

    /**
     * The boolean form of {@see assertEligible} — drives the lock icon in the
     * app, so an event a viewer cannot join is not even openable.
     */
    public function canAccess(Event $event, ?Profile $profile): bool
    {
        if ($event->community_id === null) {
            return true;
        }

        if ($profile === null) {
            return $event->visibility === EventVisibility::Public;
        }

        return $this->audienceRefusal($event, [
            'owner' => Community::query()
                ->whereKey($event->community_id)
                ->where('owner_profile_id', $profile->id)
                ->exists(),
            'member' => CommunityMember::query()
                ->where('community_id', $event->community_id)
                ->where('profile_id', $profile->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->first(),
            'follows' => CommunityFollower::query()
                ->where('community_id', $event->community_id)
                ->where('profile_id', $profile->id)
                ->exists(),
        ]) === null;
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

        // They have a seat now, so they get a ticket now.
        $this->ticketService->issueAndSend($next->refresh());

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
