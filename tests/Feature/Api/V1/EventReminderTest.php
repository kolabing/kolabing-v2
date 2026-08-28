<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventVisibility;
use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\EventSignupService;
use App\Services\NotificationReminderService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Event reminders, 24h and 1h before the start (kolabing-app#191, #252).
 *
 * These ride the existing generic reminder chain rather than a new command or
 * table: two single-sequence chains per sign-up, with a NEGATIVE cadence, so
 * `scheduled_for = starts_at->addHours(-24|-1)` means "before the anchor".
 */
class EventReminderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signing_up_well_ahead_schedules_both_reminders(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent(startsInHours: 72);
        $attendee = Profile::factory()->attendee()->create();

        app(EventSignupService::class)->signup($event, $attendee);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::EventReminder24h->value,
            'entity_id' => $event->id,
            'entity_type' => 'event',
            'cancelled_at' => null,
            'scheduled_for' => $event->starts_at->copy()->subHours(24)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::EventReminder1h->value,
            'entity_id' => $event->id,
            'entity_type' => 'event',
            'cancelled_at' => null,
            'scheduled_for' => $event->starts_at->copy()->subHour()->toDateTimeString(),
        ]);
    }

    public function test_a_late_signup_gets_the_one_hour_reminder_but_never_a_tomorrow_one(): void
    {
        Queue::fake();

        // Three hours out: the 24h mark is already behind us, so scheduling it
        // would fire a "tomorrow" push immediately. That must not happen.
        $event = $this->upcomingEvent(startsInHours: 3);
        $attendee = Profile::factory()->attendee()->create();

        app(EventSignupService::class)->signup($event, $attendee);

        $this->assertDatabaseMissing('notification_reminders', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::EventReminder24h->value,
            'entity_id' => $event->id,
            'cancelled_at' => null,
        ]);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::EventReminder1h->value,
            'entity_id' => $event->id,
            'cancelled_at' => null,
        ]);
    }

    public function test_a_waitlisted_signup_gets_no_reminders(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent(startsInHours: 72, capacity: 1);
        $first = Profile::factory()->attendee()->create();
        $waitlisted = Profile::factory()->attendee()->create();

        $service = app(EventSignupService::class);
        $service->signup($event, $first);
        $service->signup($event, $waitlisted);

        $this->assertDatabaseMissing('notification_reminders', [
            'profile_id' => $waitlisted->id,
            'entity_id' => $event->id,
            'cancelled_at' => null,
        ]);
    }

    public function test_withdrawing_the_signup_cancels_both_reminders(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent(startsInHours: 72);
        $attendee = Profile::factory()->attendee()->create();

        $service = app(EventSignupService::class);
        $service->signup($event, $attendee);
        $service->cancel($event, $attendee);

        foreach ([NotificationType::EventReminder24h, NotificationType::EventReminder1h] as $type) {
            $this->assertDatabaseMissing('notification_reminders', [
                'profile_id' => $attendee->id,
                'type' => $type->value,
                'entity_id' => $event->id,
                'cancelled_at' => null,
            ]);
        }
    }

    public function test_moving_the_event_reschedules_the_reminders(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent(startsInHours: 72);
        $attendee = Profile::factory()->attendee()->create();
        app(EventSignupService::class)->signup($event, $attendee);

        $event->update(['starts_at' => now()->addHours(100)]);
        app(NotificationReminderService::class)->syncEventRemindersForEvent($event->fresh());

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::EventReminder24h->value,
            'entity_id' => $event->id,
            'cancelled_at' => null,
            'scheduled_for' => now()->addHours(100)->copy()->subHours(24)->toDateTimeString(),
        ]);
    }

    public function test_a_due_reminder_is_delivered_once_and_carries_the_event(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent(startsInHours: 72);
        $attendee = Profile::factory()->attendee()->create();
        app(EventSignupService::class)->signup($event, $attendee);

        // Stand just inside the 24h window.
        $this->travelTo($event->starts_at->copy()->subHours(23));

        $reminderService = app(NotificationReminderService::class);
        $reminderService->sendDueReminders();
        // A second pass in the same window must not send a duplicate.
        $reminderService->sendDueReminders();

        $delivered = Notification::query()
            ->where('profile_id', $attendee->id)
            ->where('type', NotificationType::EventReminder24h->value)
            ->get();

        $this->assertCount(1, $delivered, 'the 24h reminder must be delivered exactly once');
        $this->assertSame($event->id, $delivered->first()->target_id);
        $this->assertSame('event', $delivered->first()->target_type);
    }

    private function upcomingEvent(int $startsInHours, ?int $capacity = null): Event
    {
        $host = Profile::factory()->community()->create();

        return Event::factory()->forProfile($host)->create([
            'starts_at' => now()->addHours($startsInHours),
            'ends_at' => now()->addHours($startsInHours + 2),
            'event_date' => now()->addHours($startsInHours)->toDateString(),
            'visibility' => EventVisibility::Public->value,
            'capacity' => $capacity,
        ]);
    }
}
