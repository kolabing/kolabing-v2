<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Collaboration;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The check-in door: a token that expires, a code a person can type, and a QR that
 * points somewhere a phone can actually open.
 *
 * Before this, `checkin_token` was a permanent 64-character string and the only code
 * that built a QR URL pointed at `/api/v1/events/{id}/checkin?token=…` — a route that
 * does not exist, for a POST endpoint, with the secret in a query string. A phone
 * scanning it got a 404, and the platform's core claim ("this person was in the
 * room") had no time bound at all.
 */
class EventCheckinDoorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function hostWithEvent(array $attributes = []): array
    {
        $host = Profile::factory()->community()->create();
        $community = Community::factory()->create(['owner_profile_id' => $host->id]);

        $event = Event::factory()->create(array_merge([
            'profile_id' => $host->id,
            'community_id' => $community->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
            'is_active' => false,
            'checkin_token' => null,
        ], $attributes));

        return [$host, $event];
    }

    public function test_opening_the_door_returns_everything_a_screen_needs(): void
    {
        [$host, $event] = $this->hostWithEvent();

        $response = $this->actingAs($host)
            ->postJson("/api/v1/events/{$event->id}/generate-qr")
            ->assertOk();

        $data = $response->json('data');

        $this->assertSame(64, strlen($data['checkin_token']));
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/', $data['checkin_code']);
        // The QR must carry a URL a camera can open, not an API path or a bare token.
        $this->assertSame(rtrim(config('webapp.url'), '/').'/checkin/'.$data['checkin_code'], $data['checkin_url']);
        $this->assertNotNull($data['checkin_expires_at']);
    }

    public function test_the_door_closes_by_itself_after_the_event(): void
    {
        [$host, $event] = $this->hostWithEvent();

        $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk();
        $event->refresh();

        // An hour after the event ends, not "never".
        $this->assertTrue($event->checkin_token_expires_at->isAfter($event->ends_at));
        $this->assertTrue($event->checkin_token_expires_at->isBefore($event->ends_at->copy()->addHours(2)));
    }

    public function test_an_attendee_checks_in_with_the_qr_token_or_the_typed_code(): void
    {
        [$host, $event] = $this->hostWithEvent();
        $token = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data.checkin_token');
        $code = $event->fresh()->checkin_code;

        $withToken = Profile::factory()->attendee()->create();
        $this->actingAs($withToken)->postJson('/api/v1/checkin', ['token' => $token])->assertOk();

        $withCode = Profile::factory()->attendee()->create();
        $this->actingAs($withCode)->postJson('/api/v1/checkin', ['token' => $code])->assertOk();

        // Typed by hand, so case must not decide whether someone gets in.
        $lowercase = Profile::factory()->attendee()->create();
        $this->actingAs($lowercase)->postJson('/api/v1/checkin', ['token' => strtolower($code)])->assertOk();

        $this->assertDatabaseCount('event_checkins', 3);
    }

    public function test_checking_in_twice_is_reported_as_already_in_not_as_an_error(): void
    {
        [$host, $event] = $this->hostWithEvent();
        $token = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data.checkin_token');

        $attendee = Profile::factory()->attendee()->create();
        $this->actingAs($attendee)->postJson('/api/v1/checkin', ['token' => $token])->assertOk();
        // 409 is what the page turns into "you are already checked in".
        $this->actingAs($attendee)->postJson('/api/v1/checkin', ['token' => $token])->assertStatus(409);

        $this->assertDatabaseCount('event_checkins', 1);
    }

    public function test_an_expired_token_no_longer_opens_the_door(): void
    {
        // This is the whole point of the expiry: a QR photographed at one event
        // cannot be used to manufacture attendance later.
        [$host, $event] = $this->hostWithEvent();
        $token = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data.checkin_token');

        $event->fresh()->update(['checkin_token_expires_at' => now()->subMinute()]);

        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson('/api/v1/checkin', ['token' => $token])
            ->assertStatus(422);

        $this->assertDatabaseCount('event_checkins', 0);
    }

    public function test_only_the_host_can_open_the_door(): void
    {
        [, $event] = $this->hostWithEvent();

        $this->actingAs(Profile::factory()->community()->create())
            ->postJson("/api/v1/events/{$event->id}/generate-qr")
            ->assertForbidden();
    }

    public function test_the_token_and_code_are_never_sent_to_anyone_but_the_host(): void
    {
        [$host, $event] = $this->hostWithEvent();
        $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk();

        // The host sees the door.
        $hostView = $this->actingAs($host)->getJson("/api/v1/events/{$event->id}")->assertOk();
        $this->assertTrue($hostView->json('data.checkin.is_open'));
        $this->assertNotNull($hostView->json('data.checkin.code'));
        $this->assertStringContainsString('<svg', (string) $hostView->json('data.checkin.qr_svg'));

        // Anyone else must not: holding the code is permission to be counted present.
        $otherView = $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/events/{$event->id}")
            ->assertOk();

        $this->assertNull($otherView->json('data.checkin'));
        $this->assertStringNotContainsString($event->fresh()->checkin_code, $otherView->getContent());
    }

    public function test_the_collaboration_qr_points_at_a_page_that_exists(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
        ]);

        $url = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code")
            ->assertOk()
            ->json('data.qr_code_url');

        // It used to be /api/v1/events/{id}/checkin?token=… — a 404, and a secret in
        // a query string.
        $this->assertStringStartsWith(rtrim(config('webapp.url'), '/').'/checkin/', $url);
        $this->assertStringNotContainsString('/api/v1/', $url);
        $this->assertStringNotContainsString('?token=', $url);

        // And the event it created carries a full door, not a bare token.
        $event = Event::query()->whereKey($collaboration->fresh()->event_id)->firstOrFail();
        $this->assertNotNull($event->checkin_code);
        $this->assertNotNull($event->checkin_token_expires_at);
    }
}
