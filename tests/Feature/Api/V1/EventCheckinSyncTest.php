<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\AttendeeCheckedIn;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

/**
 * Web and mobile watch the same door, so the contract has to behave the same way
 * whichever client is holding it — and one client must never break the other.
 */
class EventCheckinSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{0: Profile, 1: Event}
     */
    private function hostWithEvent(): array
    {
        $host = Profile::factory()->community()->create();
        $community = Community::factory()->create(['owner_profile_id' => $host->id]);
        $event = Event::factory()->create([
            'profile_id' => $host->id,
            'community_id' => $community->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
            'is_active' => false,
            'checkin_token' => null,
        ]);

        return [$host, $event];
    }

    public function test_reopening_the_door_keeps_the_code_that_is_already_on_screen(): void
    {
        /*
         * The two-client footgun: a host opens the door on a laptop, then opens it
         * again on a phone. Minting a fresh code there would kill the QR people are
         * queuing in front of, with nothing on the laptop to say so.
         */
        [$host, $event] = $this->hostWithEvent();

        $first = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk()->json('data');
        $second = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk()->json('data');

        $this->assertSame($first['checkin_token'], $second['checkin_token']);
        $this->assertSame($first['checkin_code'], $second['checkin_code']);
        $this->assertSame($first['checkin_url'], $second['checkin_url']);
    }

    public function test_reopening_extends_the_window_rather_than_leaving_it_to_run_out(): void
    {
        [$host, $event] = $this->hostWithEvent();

        $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk();
        $event->refresh()->update(['checkin_token_expires_at' => now()->addMinutes(5)]);

        $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->assertOk();

        $this->assertTrue($event->fresh()->checkin_token_expires_at->isAfter(now()->addHours(3)));
    }

    public function test_rotating_is_explicit_and_retires_the_old_code(): void
    {
        [$host, $event] = $this->hostWithEvent();

        $first = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data');
        $rotated = $this->actingAs($host)
            ->postJson("/api/v1/events/{$event->id}/generate-qr", ['rotate' => true])
            ->assertOk()
            ->json('data');

        $this->assertNotSame($first['checkin_token'], $rotated['checkin_token']);
        $this->assertNotSame($first['checkin_code'], $rotated['checkin_code']);

        // The point of rotating: the leaked code stops working.
        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson('/api/v1/checkin', ['token' => $first['checkin_token']])
            ->assertNotFound();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson('/api/v1/checkin', ['token' => $rotated['checkin_code']])
            ->assertOk();
    }

    public function test_an_expired_door_mints_a_fresh_code_when_reopened(): void
    {
        [$host, $event] = $this->hostWithEvent();

        $first = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data');
        $event->refresh()->update(['checkin_token_expires_at' => now()->subMinute()]);

        $reopened = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data');

        $this->assertNotSame($first['checkin_token'], $reopened['checkin_token']);
    }

    public function test_an_arrival_is_broadcast_so_every_screen_agrees_at_once(): void
    {
        EventFacade::fake([AttendeeCheckedIn::class]);

        [$host, $event] = $this->hostWithEvent();
        $token = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data.checkin_token');

        $attendee = Profile::factory()->attendee()->create();
        $this->actingAs($attendee)->postJson('/api/v1/checkin', ['token' => $token])->assertOk();

        EventFacade::assertDispatched(
            AttendeeCheckedIn::class,
            fn (AttendeeCheckedIn $broadcast): bool => $broadcast->checkin->event_id === $event->id
                && $broadcast->checkin->profile_id === $attendee->id
        );
    }

    public function test_the_broadcast_carries_the_running_total_not_just_the_arrival(): void
    {
        // A screen that missed an earlier message must still land on the right number.
        [$host, $event] = $this->hostWithEvent();
        $token = $this->actingAs($host)->postJson("/api/v1/events/{$event->id}/generate-qr")->json('data.checkin_token');

        foreach (range(1, 2) as $ignored) {
            $this->actingAs(Profile::factory()->attendee()->create())
                ->postJson('/api/v1/checkin', ['token' => $token])
                ->assertOk();
        }

        $latest = \App\Models\EventCheckin::query()->where('event_id', $event->id)->latest()->firstOrFail();
        $payload = (new AttendeeCheckedIn($latest))->broadcastWith();

        $this->assertSame(2, $payload['checked_in_count']);
        $this->assertArrayHasKey('checkin', $payload);
    }

    public function test_the_door_channel_belongs_to_the_host_alone(): void
    {
        /*
         * The stream names who walked in, so it must never widen to attendees.
         *
         * Driven with a Pusher-protocol broadcaster on purpose: the suite runs with
         * BROADCAST_CONNECTION=null, and NullBroadcaster::auth() is an empty method —
         * it authorises every channel. Asserting through /broadcasting/auth on the
         * default driver would pass no matter what the callback returned, which is
         * also why the existing chat channel has no test of its own.
         */
        config([
            'broadcasting.default' => 'reverb',
            // Credentials are absent in the test env; the signature is computed
            // locally, so placeholders are enough to exercise the callback.
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        /*
         * Channel callbacks attach to whichever broadcaster was current when
         * routes/channels.php ran — which was the null driver. Swapping the default
         * gives a fresh broadcaster with no channels registered, so re-run the
         * registrations against this one or every channel 403s for lack of a match.
         */
        require base_path('routes/channels.php');

        [$host, $event] = $this->hostWithEvent();
        $channel = 'private-event.'.$event->id.'.door';

        $this->actingAs($host)
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel])
            ->assertOk();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel])
            ->assertForbidden();

        // And a host does not gain the door of somebody else's event.
        $otherHost = Profile::factory()->community()->create();
        $this->actingAs($otherHost)
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel])
            ->assertForbidden();
    }

    public function test_the_app_link_files_stay_absent_until_the_mobile_ids_are_configured(): void
    {
        /*
         * Apple's CDN caches the association file, so a placeholder would be cached
         * too and would have to be waited out rather than fixed. Absent beats wrong.
         */
        config(['webapp.app_links.apple_app_id' => null, 'webapp.app_links.android_sha256' => null]);

        $host = config('webapp.host');
        $this->get("http://{$host}/.well-known/apple-app-site-association")->assertNotFound();
        $this->get("http://{$host}/.well-known/assetlinks.json")->assertNotFound();
    }

    public function test_the_app_link_files_hand_the_checkin_path_to_the_app(): void
    {
        config([
            'webapp.app_links.apple_app_id' => 'ABCDE12345.com.kolabing.kolabingApp',
            'webapp.app_links.android_package' => 'com.kolabing.kolabingApp',
            'webapp.app_links.android_sha256' => 'AA:BB:CC , DD:EE:FF',
        ]);

        $host = config('webapp.host');

        // One URL, two clients: the QR never has to know whether the app is installed.
        $this->get("http://{$host}/.well-known/apple-app-site-association")
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appIDs.0', 'ABCDE12345.com.kolabing.kolabingApp')
            ->assertJsonPath('applinks.details.0.components.0./', '/checkin/*');

        $this->get("http://{$host}/.well-known/assetlinks.json")
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'com.kolabing.kolabingApp')
            // Comma-separated so Play App Signing and a local release key can coexist.
            ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB:CC', 'DD:EE:FF']);
    }
}
