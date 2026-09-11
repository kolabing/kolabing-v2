<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventVisibility;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Services\EventSignupService;
use App\Services\NotificationReminderService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The `events_enabled` preference, and the push gate behind it (#252).
 *
 * Two things are being pinned here. First that the column exists and round-trips
 * through the API the app already writes to. Second — and this is the part with
 * real blast radius — that `createNotification()` now consults preferences before
 * dispatching push, WITHOUT muting anything that used to be delivered.
 */
class EventReminderPreferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_events_enabled_round_trips_through_the_api(): void
    {
        $profile = Profile::factory()->attendee()->create();

        $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', ['events_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.events_enabled', false);

        $this->actingAs($profile)
            ->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.events_enabled', false);

        $this->assertDatabaseHas('notification_preferences', [
            'profile_id' => $profile->id,
            'events_enabled' => false,
        ]);
    }

    public function test_events_enabled_defaults_on_for_a_profile_with_no_row(): void
    {
        $profile = Profile::factory()->attendee()->create();

        $this->actingAs($profile)
            ->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.events_enabled', true);
    }

    public function test_opting_out_stops_the_event_reminder_being_delivered(): void
    {
        Queue::fake();

        $event = $this->upcomingEvent();
        $attendee = Profile::factory()->attendee()->create();
        NotificationPreference::factory()->create([
            'profile_id' => $attendee->id,
            'events_enabled' => false,
        ]);

        app(EventSignupService::class)->signup($event, $attendee);
        $this->travelTo($event->starts_at->copy()->subHours(23));
        app(NotificationReminderService::class)->sendDueReminders();

        $this->assertSame(
            0,
            Notification::query()
                ->where('profile_id', $attendee->id)
                ->where('type', NotificationType::EventReminder24h->value)
                ->count(),
            'an opted-out attendee must receive no event reminder',
        );
        Queue::assertNotPushed(SendPushNotification::class);
    }

    /**
     * The regression that matters: the gate sits in front of EVERY type, so a
     * mis-mapped key would silently kill a working notification. A missing
     * preferences row must mean "everything on".
     */
    public function test_every_notification_type_still_delivers_by_default(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->attendee()->create();
        $service = app(NotificationService::class);

        foreach (NotificationType::cases() as $type) {
            $service->createNotification(
                recipient: $recipient,
                type: $type,
                title: 'Title',
                body: 'Body',
            );
        }

        $this->assertSame(
            count(NotificationType::cases()),
            Notification::query()->where('profile_id', $recipient->id)->count(),
            'no type may be dropped when the recipient has no preferences row',
        );
        Queue::assertPushed(SendPushNotification::class, count(NotificationType::cases()));
    }

    public function test_an_unrelated_opt_out_does_not_mute_event_reminders(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->attendee()->create();
        NotificationPreference::factory()->create([
            'profile_id' => $recipient->id,
            'marketing_tips' => false,
            'collaboration_updates' => false,
        ]);

        app(NotificationService::class)->createNotification(
            recipient: $recipient,
            type: NotificationType::EventReminder1h,
            title: 'Starting in 45 minutes',
            body: 'Sunset Run',
        );

        Queue::assertPushed(SendPushNotification::class, 1);
    }

    private function upcomingEvent(): Event
    {
        $host = Profile::factory()->community()->create();

        return Event::factory()->forProfile($host)->create([
            'starts_at' => now()->addHours(72),
            'ends_at' => now()->addHours(74),
            'event_date' => now()->addHours(72)->toDateString(),
            'visibility' => EventVisibility::Public->value,
        ]);
    }
}
