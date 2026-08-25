<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventSignupStatus;
use App\Models\AttendeeProfile;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSignup;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `POST /events/{event}/checkin` — the door that needs no organizer
 * (kolabing-app#144).
 *
 * The QR door needs a second person standing there with a phone out. Most small
 * community events do not have one, and without a check-in the whole challenge
 * loop is unreachable — `ChallengeCompletionService::initiate` requires an
 * `event_checkins` row for both attendees.
 *
 * What is asked instead is an RSVP and the right day, and both are tested here
 * as refusals, because they are the only thing standing between "was in the
 * room" and "tapped a button from the sofa".
 */
class EventSelfCheckinTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Unfreeze here rather than in a test body: a failing test never reaches its
     * own reset, and a leaked `now()` breaks whatever runs next.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(array $attributes = []): Event
    {
        $host = Profile::factory()->business()->create();

        return Event::factory()->forProfile($host)->create(array_merge([
            'is_active' => true,
            'event_date' => now()->toDateString(),
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(22, 0),
        ], $attributes));
    }

    private function going(Event $event, Profile $profile, EventSignupStatus $status = EventSignupStatus::Going): void
    {
        EventSignup::query()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'status' => $status->value,
        ]);
    }

    public function test_an_attendee_going_to_todays_event_can_check_themselves_in(): void
    {
        $attendee = $this->attendee();
        $event = $this->event();
        $this->going($event, $attendee);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('event_checkins', [
            'event_id' => $event->id,
            'profile_id' => $attendee->id,
        ]);
    }

    /**
     * The whole point: after this, a challenge can actually be initiated. The
     * gate that used to make the loop unreachable is an `event_checkins` row,
     * and this door produces the same row as the QR one.
     */
    public function test_it_produces_the_same_row_the_qr_door_produces(): void
    {
        $attendee = $this->attendee();
        $event = $this->event();
        $this->going($event, $attendee);

        $this->actingAs($attendee)->postJson("/api/v1/events/{$event->id}/checkin")->assertStatus(201);

        $checkin = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $attendee->id)
            ->firstOrFail();

        $this->assertNotNull($checkin->checked_in_at);
        // The consequence the QR door carries, carried here too.
        $this->assertSame(1, $attendee->attendeeProfile->fresh()->total_events_attended);
    }

    public function test_it_is_refused_without_an_rsvp(): void
    {
        $attendee = $this->attendee();
        $event = $this->event();

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(422)
            ->assertJsonPath('error', 'self_checkin_refused');

        $this->assertDatabaseMissing('event_checkins', [
            'event_id' => $event->id,
            'profile_id' => $attendee->id,
        ]);
    }

    public function test_a_cancelled_rsvp_does_not_count_as_going(): void
    {
        $attendee = $this->attendee();
        $event = $this->event();
        $this->going($event, $attendee, EventSignupStatus::Cancelled);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(422);
    }

    /**
     * The date is what stops this being "claim you were anywhere, any time".
     */
    public function test_it_is_refused_on_a_day_that_is_not_the_event_day(): void
    {
        $attendee = $this->attendee();
        $event = $this->event([
            'event_date' => now()->addDays(3)->toDateString(),
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->setTime(22, 0),
        ]);
        $this->going($event, $attendee);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(422);
    }

    public function test_a_past_event_is_refused_too(): void
    {
        $attendee = $this->attendee();
        $event = $this->event([
            'event_date' => now()->subDay()->toDateString(),
            'starts_at' => now()->subDay()->setTime(18, 0),
            'ends_at' => now()->subDay()->setTime(22, 0),
        ]);
        $this->going($event, $attendee);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(422);
    }

    /**
     * Arriving early must work: the day is the resolution, not the start time.
     *
     * The clock is frozen, and that is the point of this comment. This test used
     * to say `now()->addHours(6)` — which is "still today" only before 18:00 UTC,
     * so it passed all morning and failed in the evening. A test about what
     * counts as today cannot be written relative to whatever time it happens to
     * run at.
     */
    public function test_checking_in_before_the_event_starts_is_allowed_on_the_day(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $attendee = $this->attendee();
        $event = $this->event([
            'starts_at' => Carbon::today()->setTime(18, 0),
            'ends_at' => Carbon::today()->setTime(22, 0),
        ]);
        $this->going($event, $attendee);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(201);
    }

    /**
     * A second tap is the caller's intent already satisfied, not a failure — the
     * app reads 409 as "you are in".
     */
    public function test_a_second_check_in_is_a_409(): void
    {
        $attendee = $this->attendee();
        $event = $this->event();
        $this->going($event, $attendee);

        $this->actingAs($attendee)->postJson("/api/v1/events/{$event->id}/checkin")->assertStatus(201);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_checked_in');

        $this->assertSame(1, EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $attendee->id)
            ->count());
    }

    public function test_an_inactive_event_is_refused(): void
    {
        $attendee = $this->attendee();
        $event = $this->event(['is_active' => false]);
        $this->going($event, $attendee);

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/checkin")
            ->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $event = $this->event();

        $this->postJson("/api/v1/events/{$event->id}/checkin")->assertStatus(401);
    }
}
