<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventVisibility;
use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CalendarInvitationService;
use App\Services\EventService;
use App\Services\EventSignupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ICS calendar invitations (#252, kolabing-app#191).
 *
 * `METHOD:REQUEST` over email rather than the Google Calendar API: a real
 * invitation in Gmail, Apple Mail and Outlook for every attendee, with no OAuth
 * and no sensitive-scope review.
 */
class EventCalendarInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signing_up_sends_an_invitation_with_a_request_ics(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $attendee = Profile::factory()->attendee()->create(['email' => 'runner@example.com']);

        app(EventSignupService::class)->signup($event, $attendee);

        Mail::assertQueued(EventInvitationMail::class, function (EventInvitationMail $mail) use ($event, $attendee): bool {
            $ics = $mail->ics();

            return $mail->hasTo($attendee->email)
                && str_contains($ics, 'METHOD:REQUEST')
                && str_contains($ics, 'UID:event-'.$event->id.'@kolabing.com')
                && str_contains($ics, 'SEQUENCE:0')
                && str_contains($ics, 'DTSTART:'.$event->starts_at->utc()->format('Ymd\THis\Z'))
                && str_contains($ics, 'STATUS:CONFIRMED');
        });
    }

    public function test_a_waitlisted_signup_gets_no_invitation(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent(capacity: 1);
        $first = Profile::factory()->attendee()->create(['email' => 'first@example.com']);
        $waitlisted = Profile::factory()->attendee()->create(['email' => 'wait@example.com']);

        $service = app(EventSignupService::class);
        $service->signup($event, $first);
        $service->signup($event, $waitlisted);

        Mail::assertQueued(EventInvitationMail::class, 1);
    }

    public function test_moving_the_event_resends_with_a_higher_sequence_and_same_uid(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $attendee = Profile::factory()->attendee()->create(['email' => 'runner@example.com']);
        app(EventSignupService::class)->signup($event, $attendee);

        app(EventService::class)->update($event->fresh(), [
            'starts_at' => now()->addHours(100)->toIso8601String(),
        ]);

        $this->assertSame(1, $event->fresh()->ics_sequence);

        Mail::assertQueued(EventInvitationMail::class, function (EventInvitationMail $mail) use ($event): bool {
            $ics = $mail->ics();

            return str_contains($ics, 'UID:event-'.$event->id.'@kolabing.com')
                && str_contains($ics, 'SEQUENCE:1')
                && str_contains($ics, 'METHOD:REQUEST');
        });
    }

    public function test_renaming_the_event_does_not_churn_anyones_calendar(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $attendee = Profile::factory()->attendee()->create(['email' => 'runner@example.com']);
        app(EventSignupService::class)->signup($event, $attendee);

        app(EventService::class)->update($event->fresh(), ['name' => 'A better name']);

        $this->assertSame(0, $event->fresh()->ics_sequence);
        // Only the original invitation — a rename is not a reason to re-mail.
        Mail::assertQueued(EventInvitationMail::class, 1);
    }

    public function test_withdrawing_the_signup_cancels_only_that_attendees_entry(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $leaver = Profile::factory()->attendee()->create(['email' => 'leaver@example.com']);
        $stayer = Profile::factory()->attendee()->create(['email' => 'stayer@example.com']);

        $service = app(EventSignupService::class);
        $service->signup($event, $leaver);
        $service->signup($event, $stayer);
        $service->cancel($event, $leaver);

        Mail::assertQueued(EventInvitationMail::class, function (EventInvitationMail $mail) use ($leaver): bool {
            return $mail->hasTo($leaver->email) && str_contains($mail->ics(), 'METHOD:CANCEL');
        });

        Mail::assertNotQueued(EventInvitationMail::class, function (EventInvitationMail $mail) use ($stayer): bool {
            return $mail->hasTo($stayer->email) && str_contains($mail->ics(), 'METHOD:CANCEL');
        });
    }

    public function test_deleting_the_event_cancels_it_for_every_attendee(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $a = Profile::factory()->attendee()->create(['email' => 'a@example.com']);
        $b = Profile::factory()->attendee()->create(['email' => 'b@example.com']);

        $service = app(EventSignupService::class);
        $service->signup($event, $a);
        $service->signup($event, $b);

        app(EventService::class)->delete($event->fresh());

        Mail::assertQueued(EventInvitationMail::class, function (EventInvitationMail $mail): bool {
            return str_contains($mail->ics(), 'METHOD:CANCEL')
                && str_contains($mail->ics(), 'STATUS:CANCELLED');
        });
    }

    /**
     * `profiles.email` is NOT NULL, so this is the degenerate case the column
     * actually permits. The guard is defensive: a sign-up must never fail
     * because of a calendar side-effect.
     */
    public function test_an_attendee_with_a_blank_email_is_skipped_rather_than_erroring(): void
    {
        Queue::fake();
        Mail::fake();

        $event = $this->upcomingEvent();
        $attendee = Profile::factory()->attendee()->create(['email' => '']);

        app(EventSignupService::class)->signup($event, $attendee);

        Mail::assertNothingQueued();
    }

    public function test_the_ics_folds_long_lines_and_escapes_separators(): void
    {
        $event = $this->upcomingEvent();
        $event->update([
            'name' => 'Sunset Run; the long one, with commas'.str_repeat(' and more', 12),
        ]);
        $attendee = Profile::factory()->attendee()->create(['email' => 'runner@example.com']);

        $ics = app(CalendarInvitationService::class)->build($event->fresh(), $attendee);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'RFC 5545 caps a content line at 75 octets');
        }
        $this->assertStringContainsString('\\;', $ics);
        $this->assertStringContainsString('\\,', $ics);
    }

    private function upcomingEvent(?int $capacity = null): Event
    {
        $host = Profile::factory()->community()->create();

        return Event::factory()->forProfile($host)->create([
            'starts_at' => now()->addHours(72),
            'ends_at' => now()->addHours(74),
            'event_date' => now()->addHours(72)->toDateString(),
            'visibility' => EventVisibility::Public->value,
            'capacity' => $capacity,
            'location' => 'Barceloneta Beach',
        ]);
    }
}
