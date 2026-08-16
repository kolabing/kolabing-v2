<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Exceptions\EventCreatorEntitlementRequiredException;
use App\Exceptions\MultiKolabEventPublishValidationException;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabEventStatusEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use App\Services\Concerns\RunsSideEffects;
use App\Services\PostHog\PostHogService;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Draft, roles, publish and lifecycle for a {@see MultiKolabEvent}.
 *
 * Deliberately does not touch {@see \App\Services\ModerationService} — per
 * the founder's decision (frozen API contract §12), Multi-Kolab free-text
 * fields behave exactly like existing `kolabs.title`/`description`: no
 * proactive filter, reactive block/report only.
 */
class MultiKolabEventService
{
    use RunsSideEffects;

    /**
     * Terminal statuses no further mutation may leave.
     *
     * @var array<int, MultiKolabEventStatus>
     */
    private const TERMINAL_STATUSES = [
        MultiKolabEventStatus::Completed,
        MultiKolabEventStatus::Cancelled,
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationReminderService $notificationReminderService,
        private readonly PostHogService $postHog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(Profile $creator, array $data): MultiKolabEvent
    {
        if (! Str::of((string) ($data['title'] ?? ''))->trim()->isNotEmpty()) {
            throw new InvalidArgumentException('A title is required to start a draft.');
        }

        $event = MultiKolabEvent::query()->create([
            'creator_profile_id' => $creator->id,
            'status' => MultiKolabEventStatus::Draft,
            // Explicit in-memory default (mirrors the DB column default) —
            // without this, an unset attribute + enum cast resolves to null
            // on the freshly-created in-memory model (no refresh happens
            // after create()), which crashes the resource's `->value` access.
            'eligible_account_type' => \App\Enums\MultiKolabEligibleAccountType::Either,
            'venue_needed' => false,
            ...$this->onlyEventFields($data),
        ]);

        $this->notificationReminderService->syncMultiKolabEventDraftReminder($event);
        $this->postHog->capture($creator, 'draft_started', ['event_id' => $event->id]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MultiKolabEvent $event, array $data): MultiKolabEvent
    {
        $this->assertNotTerminal($event);

        $event->update($this->onlyEventFields($data));
        $this->notificationReminderService->syncMultiKolabEventDraftReminder($event);

        return $event->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addRole(MultiKolabEvent $event, array $data): MultiKolabRole
    {
        $this->assertNotTerminal($event);
        $this->assertValidPositionsNeeded($data);

        $role = $event->roles()->create([
            // Same in-memory-default reasoning as createDraft() above — the
            // DB column defaults (open / 1 / 0 / true) aren't reflected on
            // the freshly-created in-memory model without these.
            'status' => \App\Enums\MultiKolabRoleStatus::Open,
            'positions_needed' => 1,
            'positions_filled' => 0,
            'required' => true,
            ...$this->onlyRoleFields($data),
        ]);

        $this->postHog->capture($event->creatorProfile, 'role_added', [
            'event_id' => $event->id,
            'role_id' => $role->id,
        ]);

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRole(MultiKolabRole $role, array $data): MultiKolabRole
    {
        $this->assertNotTerminal($role->event);

        if (array_key_exists('positions_needed', $data)) {
            $this->assertValidPositionsNeeded($data);
        }

        $role->update($this->onlyRoleFields($data));

        return $role->fresh();
    }

    public function removeRole(MultiKolabRole $role): void
    {
        $this->assertNotTerminal($role->event);

        $hasAcceptedApplication = $role->applications()
            ->where('status', MultiKolabRoleApplicationStatus::Accepted)
            ->exists();

        if ($hasAcceptedApplication) {
            throw new InvalidArgumentException(
                'This role has an accepted application and cannot be removed.'
            );
        }

        $role->delete();
    }

    /**
     * @throws EventCreatorEntitlementRequiredException
     * @throws MultiKolabEventPublishValidationException
     */
    public function publish(MultiKolabEvent $event, Profile $actor): MultiKolabEvent
    {
        $this->assertTransition($event, MultiKolabEventStatus::Draft, 'publish');

        if (! $actor->hasEventCreatorEntitlement()) {
            throw new EventCreatorEntitlementRequiredException(
                'Event Creator access is required to publish.'
            );
        }

        $this->assertPublishable($event);

        $event->update([
            'status' => MultiKolabEventStatus::Recruiting,
            'published_at' => now(),
        ]);

        $this->recordStatusEvent($event, MultiKolabEventStatus::Recruiting, $actor);
        $this->notificationReminderService->cancelMultiKolabEventDraftReminder($event);
        $this->postHog->capture($actor, 'event_published', ['event_id' => $event->id]);

        return $event->fresh();
    }

    public function confirm(MultiKolabEvent $event, Profile $actor): MultiKolabEvent
    {
        $this->assertTransition($event, MultiKolabEventStatus::Recruiting, 'confirm');

        $event->update([
            'status' => MultiKolabEventStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->recordStatusEvent($event, MultiKolabEventStatus::Confirmed, $actor);

        $fresh = $event->fresh();
        // Best-effort, post-commit — a notification/analytics failure must
        // never turn this already-successful confirmation into an uncaught
        // 500 (see RunsSideEffects).
        $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabEventConfirmed($fresh));
        $this->runSideEffect(fn () => $this->postHog->capture($actor, 'event_confirmed', ['event_id' => $event->id]));

        return $fresh;
    }

    public function complete(MultiKolabEvent $event, Profile $actor): MultiKolabEvent
    {
        $this->assertTransition($event, MultiKolabEventStatus::Confirmed, 'complete');

        $event->update([
            'status' => MultiKolabEventStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->recordStatusEvent($event, MultiKolabEventStatus::Completed, $actor);

        return $event->fresh();
    }

    public function cancel(MultiKolabEvent $event, Profile $actor, string $reason): MultiKolabEvent
    {
        if (in_array($event->status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidArgumentException(
                "An event that is already {$event->status->value} cannot be cancelled."
            );
        }

        $event->update([
            'status' => MultiKolabEventStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->recordStatusEvent($event, MultiKolabEventStatus::Cancelled, $actor, $reason);
        $this->notificationReminderService->cancelMultiKolabEventDraftReminder($event);

        $fresh = $event->fresh();
        $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabEventCancelled($fresh, $reason));
        $this->runSideEffect(fn () => $this->postHog->capture($actor, 'event_cancelled', ['event_id' => $event->id]));

        return $fresh;
    }

    /**
     * Refuse any mutation (event fields or roles) once the event is
     * completed or cancelled — both are terminal and, per the Global
     * Constraints, cancelled events remain recorded rather than edited away.
     */
    private function assertNotTerminal(MultiKolabEvent $event): void
    {
        if (in_array($event->status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidArgumentException(
                "This event is {$event->status->value} and can no longer be edited."
            );
        }
    }

    private function assertTransition(
        MultiKolabEvent $event,
        MultiKolabEventStatus $requiredFrom,
        string $action,
    ): void {
        if ($event->status !== $requiredFrom) {
            throw new InvalidArgumentException(
                "Cannot {$action} an event with status \"{$event->status->value}\" — expected \"{$requiredFrom->value}\"."
            );
        }
    }

    /**
     * @throws MultiKolabEventPublishValidationException
     */
    private function assertPublishable(MultiKolabEvent $event): void
    {
        $errors = [];

        if (! Str::of((string) ($event->description ?? ''))->trim()->isNotEmpty()) {
            $errors['description'] = ['A description is required to publish.'];
        }

        if ($event->roles()->count() === 0) {
            $errors['roles'] = ['At least one role is required.'];
        }

        if (is_string($event->rsvp_url) && $event->rsvp_url !== '' && ! Str::startsWith($event->rsvp_url, 'https://')) {
            $errors['rsvp_url'] = ['The RSVP link must use https://.'];
        }

        // Venue-needed consistency: an event that still needs a venue must at
        // least specify the target city so venue-role applicants know where
        // they'd be partnering.
        if ($event->venue_needed && ! Str::of((string) ($event->city ?? ''))->trim()->isNotEmpty()) {
            $errors['city'] = ['A city is required when the event still needs a venue.'];
        }

        if ($event->date_mode === 'exact' && $event->event_date === null) {
            $errors['event_date'] = ['An event date is required for an exact-date event.'];
        }

        if ($event->date_mode === 'range' && ($event->date_range_start === null || $event->date_range_end === null)) {
            $errors['date_range'] = ['Both a start and end date are required for a date-range event.'];
        }

        if ($errors !== []) {
            throw new MultiKolabEventPublishValidationException($errors);
        }
    }

    private function assertValidPositionsNeeded(array $data): void
    {
        if (array_key_exists('positions_needed', $data) && (int) $data['positions_needed'] < 1) {
            throw new InvalidArgumentException('positions_needed must be at least 1.');
        }
    }

    private function recordStatusEvent(
        MultiKolabEvent $event,
        MultiKolabEventStatus $status,
        Profile $actor,
        ?string $reason = null,
    ): void {
        MultiKolabEventStatusEvent::query()->create([
            'multi_kolab_event_id' => $event->id,
            'status' => $status,
            'actor_profile_id' => $actor->id,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyEventFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'title',
            'description',
            'value_summary',
            'venue_needed',
            'date_mode',
            'event_date',
            'date_range_start',
            'date_range_end',
            'city',
            'category',
            'rsvp_url',
            'eligible_account_type',
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyRoleFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'title',
            'eligible_account_type',
            'positions_needed',
            'required',
            'need',
            'receive',
            'compensation_type',
            'requirements',
            'details',
        ]));
    }
}
