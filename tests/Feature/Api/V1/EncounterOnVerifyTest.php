<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Encounter;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChallengeCompletionService;
use App\Services\EncounterService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The People Layer, written from a verification (#244).
 *
 * `challenge_completions` records an action. These tests are about the thing it
 * never recorded: that two people **met**, how many times, and what that is
 * worth the second and third time round.
 *
 * The rule under nearly all of them is the same one: **a meeting is an EVENT,
 * not a challenge.**
 */
class EncounterOnVerifyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function event(): Event
    {
        $host = Profile::factory()->business()->create();

        return Event::factory()->forProfile($host)->create(['is_active' => true]);
    }

    private function pendingCompletion(
        Profile $challenger,
        Profile $verifier,
        Event $event,
        ?string $photoUrl = null
    ): ChallengeCompletion {
        $challenge = Challenge::factory()->system()->easy()->create();

        return ChallengeCompletion::query()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'status' => ChallengeCompletionStatus::Pending->value,
            'points_earned' => 0,
            'proof_photo_url' => $photoUrl,
        ]);
    }

    private function service(): ChallengeCompletionService
    {
        return app(ChallengeCompletionService::class);
    }

    public function test_verifying_records_the_meeting_in_both_directions(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();
        $event = $this->event();

        $this->service()->verify($b, $this->pendingCompletion($a, $b, $event));

        // Two rows, one per viewer. That is what makes "who have I met" an
        // index scan on profile_id rather than an OR across two columns.
        $this->assertDatabaseHas('encounters', [
            'profile_id' => $a->id,
            'other_profile_id' => $b->id,
            'event_id' => $event->id,
            'times_met' => 1,
        ]);
        $this->assertDatabaseHas('encounters', [
            'profile_id' => $b->id,
            'other_profile_id' => $a->id,
            'event_id' => $event->id,
            'times_met' => 1,
        ]);
    }

    public function test_ten_challenges_in_one_night_is_still_one_meeting(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();
        $event = $this->event();

        for ($i = 0; $i < 10; $i++) {
            $this->service()->verify($b, $this->pendingCompletion($a, $b, $event));
        }

        // The whole anti-farming design in one assertion: the pair shared one
        // room, so they met once, however many challenges they got through.
        $this->assertSame(
            1,
            Encounter::query()->where('profile_id', $a->id)->where('other_profile_id', $b->id)->count()
        );
        $this->assertSame(
            1,
            Encounter::query()->where('profile_id', $b->id)->where('other_profile_id', $a->id)->count()
        );
    }

    public function test_a_second_event_is_a_second_meeting(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();

        $this->service()->verify($b, $this->pendingCompletion($a, $b, $this->event()));
        $this->service()->verify($b, $this->pendingCompletion($a, $b, $this->event()));

        $rows = Encounter::query()
            ->where('profile_id', $a->id)
            ->where('other_profile_id', $b->id)
            ->orderBy('times_met')
            ->pluck('times_met')
            ->all();

        // Each row is a historical fact — "at this event it was our Nth time" —
        // so the first row keeps saying 1 forever.
        $this->assertSame([1, 2], $rows);
    }

    public function test_the_photo_the_pair_took_lands_on_the_meeting(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();
        $event = $this->event();

        $this->service()->verify(
            $b,
            $this->pendingCompletion($a, $b, $event, 'https://cdn.kolabing.com/proof/selfie.jpg')
        );

        $this->assertDatabaseHas('encounters', [
            'profile_id' => $a->id,
            'other_profile_id' => $b->id,
            'proof_photo_url' => 'https://cdn.kolabing.com/proof/selfie.jpg',
        ]);
    }

    public function test_a_photo_from_a_later_challenge_that_night_still_lands(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();
        $event = $this->event();

        // First challenge of the night had no photo; the second did. The
        // meeting row already exists by then, and it should still get the
        // picture rather than staying blank forever.
        $this->service()->verify($b, $this->pendingCompletion($a, $b, $event));
        $this->service()->verify(
            $b,
            $this->pendingCompletion($a, $b, $event, 'https://cdn.kolabing.com/proof/late.jpg')
        );

        $this->assertDatabaseHas('encounters', [
            'profile_id' => $a->id,
            'other_profile_id' => $b->id,
            'proof_photo_url' => 'https://cdn.kolabing.com/proof/late.jpg',
        ]);
    }

    public function test_an_encounter_is_not_a_friendship(): void
    {
        $a = $this->attendee();
        $b = $this->attendee();

        $this->service()->verify($b, $this->pendingCompletion($a, $b, $this->event()));

        // A fact, not a relationship. The app offers "Add friend" on the
        // reveal; the person decides. Nothing here decides for them.
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_crossing_a_rung_pays_the_bonus_once_to_both(): void
    {
        config(['gamification.pair_ladder' => [
            ['at' => 1, 'key' => 'met', 'bonus' => 0],
            ['at' => 2, 'key' => 'regulars', 'bonus' => 10],
        ]]);

        $a = $this->attendee();
        $b = $this->attendee();

        $this->service()->verify($b, $this->pendingCompletion($a, $b, $this->event()));
        $afterFirst = $a->attendeeProfile()->first()->total_points;

        $completion = $this->pendingCompletion($a, $b, $this->event());
        $result = $this->service()->verify($b, $completion);

        $challengePoints = $completion->challenge->points;

        foreach ([$a, $b] as $participant) {
            $this->assertSame(
                $afterFirst + $challengePoints + 10,
                $participant->attendeeProfile()->first()->total_points,
                'both sides of a pair get the ladder bonus, not just the challenger'
            );
        }

        $this->assertTrue($result->pairLevel['just_levelled_up']);
        $this->assertSame(10, $result->pairLevel['bonus_awarded']);
    }

    public function test_a_meeting_below_the_next_rung_pays_no_bonus(): void
    {
        config(['gamification.pair_ladder' => [
            ['at' => 1, 'key' => 'met', 'bonus' => 0],
            ['at' => 5, 'key' => 'crew', 'bonus' => 25],
        ]]);

        $a = $this->attendee();
        $b = $this->attendee();

        $result = $this->service()->verify($b, $this->pendingCompletion($a, $b, $this->event()));

        $this->assertFalse($result->pairLevel['just_levelled_up']);
        $this->assertSame(0, $result->pairLevel['bonus_awarded']);
        $this->assertSame(5, $result->pairLevel['next_at']);
        $this->assertSame('met', $result->pairLevel['key']);
    }

    public function test_a_broken_encounter_write_never_costs_anyone_their_points(): void
    {
        // The rule this whole wiring is built around: the points are the
        // contract between two people standing in a room, and the ledger is
        // bookkeeping that happens afterwards.
        $this->app->bind(EncounterService::class, fn (): EncounterService => new class extends EncounterService
        {
            public function recordChallengeMeeting(ChallengeCompletion $completion): ?array
            {
                throw new \RuntimeException('the ledger is on fire');
            }
        });

        $a = $this->attendee();
        $b = $this->attendee();
        $completion = $this->pendingCompletion($a, $b, $this->event());

        $result = $this->service()->verify($b, $completion);

        $this->assertSame(ChallengeCompletionStatus::Verified, $result->status);
        $this->assertSame(
            $completion->challenge->points,
            $a->attendeeProfile()->first()->total_points
        );
        $this->assertNull($result->pairLevel);
    }

    public function test_the_rung_helper_reads_the_configured_ladder(): void
    {
        config(['gamification.pair_ladder' => [
            ['at' => 1, 'key' => 'met', 'bonus' => 0],
            ['at' => 3, 'key' => 'regulars', 'bonus' => 10],
            ['at' => 5, 'key' => 'crew', 'bonus' => 25],
        ]]);

        $service = app(EncounterService::class);

        $this->assertSame('met', $service->rungFor(1)['key']);
        $this->assertSame(3, $service->rungFor(1)['next_at']);

        $this->assertSame('regulars', $service->rungFor(3)['key']);
        $this->assertTrue($service->rungFor(3)['just_levelled_up']);

        // Between rungs: still a regular, nothing paid.
        $this->assertSame('regulars', $service->rungFor(4)['key']);
        $this->assertFalse($service->rungFor(4)['just_levelled_up']);

        // Top of the ladder has nowhere further to go.
        $this->assertNull($service->rungFor(5)['next_at']);
    }
}
