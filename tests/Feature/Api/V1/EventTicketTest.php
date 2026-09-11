<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Mail\EventTicketMail;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use App\Services\EventSignupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tickets: a seat someone can be let in with.
 *
 * The direction matters and is the whole point of this feature. The door already
 * worked one way — the host displays a QR, attendees scan it — and that is untouched.
 * A ticket is the other way: the attendee carries proof and the host scans *them*.
 * So the authorisation rule inverts too, and these tests hold that line: the person
 * being admitted is not the person authenticated.
 */
class EventTicketTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): Profile
    {
        return Profile::factory()->business()->create();
    }

    private function happening(Profile $host, array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'profile_id' => $host->id,
            'community_id' => null,
            'visibility' => EventVisibility::Public->value,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(3),
            'event_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    private function attendee(): Profile
    {
        return Profile::factory()->attendee()->create();
    }

    // ── Issuing ──────────────────────────────────────────────────────────

    /**
     * A public happening with no community must be joinable. This is the case the
     * whole Kolab funnel runs through, and it used to be impossible: the sign-up
     * service refused anything without a `community_id`.
     */
    public function test_a_public_happening_with_no_community_can_be_joined(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());
        $attendee = $this->attendee();

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertOk();

        $this->assertDatabaseHas('event_signups', [
            'event_id' => $event->id,
            'profile_id' => $attendee->id,
            'status' => EventSignupStatus::Going->value,
        ]);
    }

    public function test_taking_a_seat_issues_a_ticket_and_emails_it(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());
        $attendee = $this->attendee();

        $this->actingAs($attendee)->postJson("/api/v1/events/{$event->id}/signup")->assertOk();

        $signup = EventSignup::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertNotNull($signup->ticket_code);
        $this->assertSame(10, strlen((string) $signup->ticket_code));
        $this->assertNotNull($signup->ticket_issued_at);
        Mail::assertQueued(EventTicketMail::class);
    }

    /** Unambiguous alphabet: a doorkeeper reads these aloud and types them in bad light. */
    public function test_a_ticket_code_avoids_characters_that_get_misread(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());

        $this->actingAs($this->attendee())->postJson("/api/v1/events/{$event->id}/signup")->assertOk();

        $code = (string) EventSignup::query()->where('event_id', $event->id)->value('ticket_code');

        $this->assertSame(0, preg_match('/[O0I1L]/', $code), "Code {$code} contains an ambiguous character.");
    }

    /** Re-issuing would change the code already sitting in someone's inbox. */
    public function test_signing_up_again_does_not_mint_a_second_code(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());
        $attendee = $this->attendee();

        $this->actingAs($attendee)->postJson("/api/v1/events/{$event->id}/signup")->assertOk();
        $first = EventSignup::query()->where('event_id', $event->id)->value('ticket_code');

        $this->actingAs($attendee)->postJson("/api/v1/events/{$event->id}/signup")->assertOk();
        $second = EventSignup::query()->where('event_id', $event->id)->value('ticket_code');

        $this->assertSame($first, $second);
    }

    /** A waitlisted place is not a seat, and a ticket that might not be honoured is worse than none. */
    public function test_a_waitlisted_signup_gets_no_ticket_until_it_is_promoted(): void
    {
        Mail::fake();
        $event = $this->happening($this->host(), ['capacity' => 1]);
        $first = $this->attendee();
        $second = $this->attendee();

        $this->actingAs($first)->postJson("/api/v1/events/{$event->id}/signup")->assertOk();
        $this->actingAs($second)->postJson("/api/v1/events/{$event->id}/signup")->assertOk();

        $waitlisted = EventSignup::query()->where('profile_id', $second->id)->firstOrFail();
        $this->assertSame(EventSignupStatus::Waitlisted, $waitlisted->status);
        $this->assertNull($waitlisted->ticket_code);

        // The seat frees; the head of the waitlist gets it, and gets a ticket with it.
        $this->actingAs($first)->deleteJson("/api/v1/events/{$event->id}/signup")->assertOk();

        $promoted = $waitlisted->refresh();
        $this->assertSame(EventSignupStatus::Going, $promoted->status);
        $this->assertNotNull($promoted->ticket_code);
    }

    // ── The door ─────────────────────────────────────────────────────────

    public function test_the_host_admits_the_holder_and_attendance_is_recorded(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        $this->actingAs($host)
            ->postJson("/api/v1/tickets/{$code}/admit")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Attendance lives in one place however someone got in.
        $this->assertDatabaseHas('event_checkins', [
            'event_id' => $event->id,
            'profile_id' => $attendee->id,
        ]);
    }

    /** Lower case in, upper case stored — doorkeepers type however they type. */
    public function test_a_code_is_accepted_whatever_case_it_is_typed_in(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = (string) EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        $this->actingAs($host)
            ->postJson('/api/v1/tickets/'.strtolower($code).'/admit')
            ->assertOk();
    }

    /** Holding the code is not permission to admit — hosting the event is. */
    public function test_someone_who_is_not_the_host_cannot_admit_anyone(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        // Not even the holder may admit themselves: that is the host's act.
        $this->actingAs($attendee)
            ->postJson("/api/v1/tickets/{$code}/admit")
            ->assertStatus(403);

        $this->actingAs($this->host())
            ->postJson("/api/v1/tickets/{$code}/admit")
            ->assertStatus(403);

        $this->assertDatabaseCount('event_checkins', 0);
    }

    /**
     * At a busy door the same ticket gets scanned twice constantly. 409 with a clear
     * message, not a generic error the doorkeeper has to interpret.
     */
    public function test_scanning_the_same_ticket_twice_says_so_plainly(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        $this->actingAs($host)->postJson("/api/v1/tickets/{$code}/admit")->assertOk();

        $this->actingAs($host)
            ->postJson("/api/v1/tickets/{$code}/admit")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This ticket has already been used.');

        $this->assertDatabaseCount('event_checkins', 1);
    }

    public function test_a_cancelled_seat_cannot_be_admitted(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        $this->actingAs($attendee)->deleteJson("/api/v1/events/{$event->id}/signup")->assertOk();

        $this->actingAs($host)->postJson("/api/v1/tickets/{$code}/admit")->assertStatus(403);
        $this->assertDatabaseCount('event_checkins', 0);
    }

    public function test_an_unknown_code_is_a_404(): void
    {
        $this->actingAs($this->host())
            ->postJson('/api/v1/tickets/NOTAREALCODE/admit')
            ->assertStatus(404);
    }

    // ── The wallet ───────────────────────────────────────────────────────

    public function test_the_holder_sees_their_own_tickets_with_a_scannable_qr(): void
    {
        Mail::fake();
        $event = $this->happening($this->host(), ['name' => 'Sunday Run and Brunch']);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);

        $response = $this->actingAs($attendee)->getJson('/api/v1/me/tickets')->assertOk();

        $response->assertJsonPath('data.0.event.name', 'Sunday Run and Brunch');
        $response->assertJsonPath('data.0.used_at', null);
        // Inline SVG, so the door does not need a second request on a bad connection.
        $this->assertStringContainsString('<svg', (string) $response->json('data.0.qr_svg'));
        $this->assertStringContainsString(
            '/admit/'.$response->json('data.0.code'),
            (string) $response->json('data.0.admit_url')
        );
    }

    public function test_a_wallet_holds_only_its_owners_tickets(): void
    {
        Mail::fake();
        $event = $this->happening($this->host());
        $mine = $this->attendee();
        $theirs = $this->attendee();

        app(EventSignupService::class)->signup($event, $mine);
        app(EventSignupService::class)->signup($event, $theirs);

        $this->actingAs($mine)
            ->getJson('/api/v1/me/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_used_ticket_reports_when_it_was_used(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');
        $this->actingAs($host)->postJson("/api/v1/tickets/{$code}/admit")->assertOk();

        $this->actingAs($attendee)
            ->getJson('/api/v1/me/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.code', $code);

        $this->assertNotNull(
            $this->actingAs($attendee)->getJson('/api/v1/me/tickets')->json('data.0.used_at')
        );
    }

    /** Whether a code exists is itself information, so a stranger gets 404, not 403. */
    public function test_a_stranger_cannot_even_learn_that_a_ticket_exists(): void
    {
        Mail::fake();
        $host = $this->host();
        $event = $this->happening($host);
        $attendee = $this->attendee();

        app(EventSignupService::class)->signup($event, $attendee);
        $code = EventSignup::query()->where('profile_id', $attendee->id)->value('ticket_code');

        $this->actingAs($this->attendee())->getJson("/api/v1/tickets/{$code}")->assertStatus(404);

        // The holder and the host may both read it — the host because someone will
        // read a code out loud when a QR will not scan.
        $this->actingAs($attendee)->getJson("/api/v1/tickets/{$code}")->assertOk();
        $this->actingAs($host)->getJson("/api/v1/tickets/{$code}")->assertOk();
    }
}
