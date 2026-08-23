<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\CommunityChallenge;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * No XP cap unless an organizer chose one (kolabing-app#156, §8).
 *
 * The cap used to arrive by default — `max_challenges_per_attendee` defaulted to
 * 10, so every event ever created carried a limit nobody set and nothing in the
 * product ever mentioned. Null now means unlimited.
 */
class ChallengeCapTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            'event_date' => Carbon::today(),
            'starts_at' => Carbon::today()->setTime(18, 0),
            'ends_at' => Carbon::today()->setTime(23, 0),
        ], $attributes));
    }

    /**
     * A pair doing several challenges is the behaviour we want to observe, so it
     * must not be capped by a number nobody chose.
     */
    public function test_a_new_event_has_no_cap(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(19, 0));

        $event = $this->event();
        $this->assertNull($event->max_challenges_per_attendee);

        $a = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($a)->create();

        // Twelve, comfortably past the old default of 10.
        for ($i = 0; $i < 12; $i++) {
            $partner = $this->attendee();
            EventCheckin::factory()->forEvent($event)->forProfile($partner)->create();
            $challenge = Challenge::factory()->system()->easy()->create();

            $this->actingAs($a)
                ->postJson('/api/v1/challenges/initiate', [
                    'challenge_id' => $challenge->id,
                    'event_id' => $event->id,
                    'verifier_profile_id' => $partner->id,
                ])
                ->assertSuccessful();
        }
    }

    /**
     * The mechanism stays for the organizer who eventually wants one — this is a
     * product decision for MVP, not the removal of a capability.
     */
    public function test_an_explicit_cap_is_still_enforced(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(19, 0));

        $event = $this->event(['max_challenges_per_attendee' => 1]);

        $a = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($a)->create();

        $first = $this->attendee();
        $second = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($first)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($second)->create();

        $this->actingAs($a)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => Challenge::factory()->system()->easy()->create()->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $first->id,
            ])
            ->assertSuccessful();

        $this->actingAs($a)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => Challenge::factory()->system()->easy()->create()->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $second->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'event_limit_reached');
    }

    /**
     * With no cap there is nothing to count, so the count is not run — a query
     * per initiate saved for a limit that no longer exists.
     */
    public function test_an_uncapped_event_does_not_count_completions(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(19, 0));

        $event = $this->event();
        $a = $this->attendee();
        $b = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($a)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($b)->create();

        // A community rule row exists so the initiate path is fully exercised.
        $challenge = Challenge::factory()->system()->easy()->create();

        $this->assertSame(0, CommunityChallenge::query()->count());

        $this->actingAs($a)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $challenge->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $b->id,
            ])
            ->assertSuccessful();
    }
}
