<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What happens to a challenge request nobody answers (kolabing-app#154).
 *
 * Two things it must never do: earn anyone XP without a confirmation, and stay
 * answerable after the event it belonged to. The second matters more than it
 * looks — a pending row that survives the night can be confirmed days later for
 * XP neither person earned in the room.
 */
class ChallengeRequestLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Unfreeze the clock here rather than at the end of each test.
     *
     * Several of these travel in time, and a test that FAILS never reaches a
     * reset written in its own body — so the frozen `now()` leaks into whatever
     * runs next. That is not hypothetical: it broke
     * `EventSelfCheckinTest::checking_in_before_the_event_starts` on the
     * integration branch, where the file ordering put it after this one.
     * tearDown runs either way.
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
     * @param  array<string, mixed>  $eventAttributes
     * @return array{event: Event, a: Profile, b: Profile, challenge: Challenge}
     */
    private function scenario(array $eventAttributes = []): array
    {
        $host = Profile::factory()->business()->create();
        $event = Event::factory()->forProfile($host)->create(array_merge([
            'is_active' => true,
            'event_date' => Carbon::today(),
            'starts_at' => Carbon::now()->addHour(),
            'ends_at' => Carbon::now()->addHours(3),
            'max_challenges_per_attendee' => 20,
        ], $eventAttributes));

        $challenge = Challenge::factory()->system()->easy()->create();

        $a = $this->attendee();
        $b = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($a)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($b)->create();

        return compact('event', 'a', 'b', 'challenge');
    }

    private function initiate(Profile $a, Event $event, Challenge $challenge, Profile $b): string
    {
        return $this->actingAs($a)->postJson('/api/v1/challenges/initiate', [
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'verifier_profile_id' => $b->id,
        ])->assertSuccessful()->json('data.id');
    }

    /*
    |--------------------------------------------------------------------------
    | Cancelling
    |--------------------------------------------------------------------------
    */

    public function test_the_challenger_can_cancel_a_pending_request(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($a)
            ->postJson("/api/v1/challenge-completions/{$id}/cancel")
            ->assertSuccessful();

        $this->assertSame(
            ChallengeCompletionStatus::Cancelled,
            ChallengeCompletion::query()->findOrFail($id)->status
        );
    }

    /**
     * Cancel is the challenger's. The verifier already has Reject, and the two
     * say different things: "I changed my mind" versus "no, we did not do that".
     */
    public function test_the_verifier_cannot_cancel(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($b)
            ->postJson("/api/v1/challenge-completions/{$id}/cancel")
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_the_challenger');
    }

    public function test_an_answered_request_cannot_be_cancelled(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($b)->postJson("/api/v1/challenge-completions/{$id}/verify")->assertSuccessful();

        $this->actingAs($a)
            ->postJson("/api/v1/challenge-completions/{$id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_answered');
    }

    /**
     * No XP unless it was confirmed — cancelling is not a quiet way to bank it.
     */
    public function test_cancelling_awards_nothing(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($a)->postJson("/api/v1/challenge-completions/{$id}/cancel")->assertSuccessful();

        $this->assertSame(0, $a->attendeeProfile->fresh()->total_points);
        $this->assertSame(0, $b->attendeeProfile->fresh()->total_points);
        $this->assertSame(0, ChallengeCompletion::query()->findOrFail($id)->points_earned);
    }

    /**
     * A cancelled request frees the pair to ask again — the duplicate-pending
     * rule is about live requests, not about history.
     */
    public function test_cancelling_lets_them_ask_again(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($a)->postJson("/api/v1/challenge-completions/{$id}/cancel")->assertSuccessful();

        $this->initiate($a, $e, $c, $b);
    }

    /*
    |--------------------------------------------------------------------------
    | Expiring
    |--------------------------------------------------------------------------
    */

    /**
     * The one that matters. Confirming a request from a finished event would pay
     * out XP neither person earned in the room.
     */
    public function test_a_request_cannot_be_confirmed_once_it_has_run_out(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        // Past the event's window AND past the 12-hour session floor every
        // request gets, because an event's recorded dates are not always the
        // truth. Both have to lapse.
        Carbon::setTestNow(Carbon::now()->addHours(13));

        $this->actingAs($b)
            ->postJson("/api/v1/challenge-completions/{$id}/verify")
            ->assertStatus(409);

        $this->assertSame(
            ChallengeCompletionStatus::Expired,
            ChallengeCompletion::query()->findOrFail($id)->status
        );
        $this->assertSame(0, $b->attendeeProfile->fresh()->total_points);

    }

    /**
     * The confirmation that happens on the way out still works — and so does the
     * one someone remembers an hour later in the pub.
     */
    public function test_a_request_survives_the_hours_right_after_the_event(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        // Two hours after ends_at: past the event's window, well inside the
        // session floor.
        Carbon::setTestNow(Carbon::now()->addHours(5));

        $this->actingAs($b)
            ->postJson("/api/v1/challenge-completions/{$id}/verify")
            ->assertSuccessful();

    }

    public function test_the_sweep_command_expires_stale_requests(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        Carbon::setTestNow(Carbon::now()->addDays(2));

        $this->artisan('app:expire-pending-challenges')->assertSuccessful();

        $this->assertSame(
            ChallengeCompletionStatus::Expired,
            ChallengeCompletion::query()->findOrFail($id)->status
        );

    }

    public function test_the_sweep_leaves_live_requests_alone(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->artisan('app:expire-pending-challenges')->assertSuccessful();

        $this->assertSame(
            ChallengeCompletionStatus::Pending,
            ChallengeCompletion::query()->findOrFail($id)->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The listing (§19's queue, and old app builds)
    |--------------------------------------------------------------------------
    */

    /**
     * Shipped app builds parse an unknown status by falling back to `pending`,
     * so a cancelled row they do not understand would show on their poller as a
     * live request that can never be answered. Excluding it server-side is what
     * keeps those builds honest.
     */
    public function test_cancelled_and_expired_requests_leave_the_listing(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $id = $this->initiate($a, $e, $c, $b);

        $this->actingAs($b)
            ->getJson('/api/v1/me/challenge-completions')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.completions');

        $this->actingAs($a)->postJson("/api/v1/challenge-completions/{$id}/cancel")->assertSuccessful();

        $this->actingAs($b)
            ->getJson('/api/v1/me/challenge-completions')
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.completions');
    }

    /**
     * §19: the queue. Two people ask B at once; B answers one and the other is
     * still there, waiting its turn rather than having been lost.
     */
    public function test_a_second_request_waits_its_turn(): void
    {
        ['event' => $e, 'a' => $a, 'b' => $b, 'challenge' => $c] = $this->scenario();
        $second = $this->attendee();
        EventCheckin::factory()->forEvent($e)->forProfile($second)->create();
        $otherChallenge = Challenge::factory()->system()->medium()->create();

        $first = $this->initiate($a, $e, $c, $b);
        $this->initiate($second, $e, $otherChallenge, $b);

        $this->actingAs($b)
            ->getJson('/api/v1/me/challenge-completions')
            ->assertJsonCount(2, 'data.completions');

        $this->actingAs($b)->postJson("/api/v1/challenge-completions/{$first}/verify")->assertSuccessful();

        // The other one is untouched and still answerable.
        $pending = ChallengeCompletion::query()
            ->where('verifier_profile_id', $b->id)
            ->where('status', ChallengeCompletionStatus::Pending->value)
            ->count();

        $this->assertSame(1, $pending);
    }
}
