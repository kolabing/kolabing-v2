<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\JoinPolicy;
use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityChallenge;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The challenge library, and communities choosing from it (kolabing-app#150).
 *
 * The decisions these tests exist to hold still:
 *
 *  - a community that has curated NOTHING gets the whole library, because that
 *    is today's behaviour and the alternative blanks every existing community's
 *    events on deploy day;
 *  - "curated to nothing" and "not curated" are different states;
 *  - repeating a challenge with the same person is the community's call, and
 *    two PENDING requests for it never are.
 */
class ChallengeLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function leader(): Profile
    {
        return Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);
    }

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function community(Profile $owner): Community
    {
        return Community::query()->create([
            'owner_profile_id' => $owner->id,
            'name' => 'Library Club '.uniqid(),
            'slug' => 'library-'.uniqid(),
            'type' => 'running',
            'join_policy' => JoinPolicy::Open->value,
        ]);
    }

    private function event(Profile $host, ?Community $community): Event
    {
        return Event::factory()->forProfile($host)->create([
            'community_id' => $community?->id,
            'is_active' => true,
            'checkin_token' => 'lib-'.uniqid(),
            'event_date' => Carbon::today(),
            'max_challenges_per_attendee' => 20,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    */

    /**
     * The most important test in this file: nothing curated behaves exactly as
     * it did before any of this existed.
     */
    public function test_a_community_that_curated_nothing_gets_the_whole_library(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community);

        Challenge::factory()->system()->easy()->create(['name' => 'Library A']);
        Challenge::factory()->system()->easy()->create(['name' => 'Library B']);

        $names = collect(
            $this->actingAs($this->attendee())
                ->getJson("/api/v1/events/{$event->id}/challenges")
                ->json('data.challenges')
        )->pluck('name');

        $this->assertContains('Library A', $names);
        $this->assertContains('Library B', $names);
    }

    public function test_a_curated_community_gets_only_what_it_chose(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community);

        $chosen = Challenge::factory()->system()->easy()->create(['name' => 'Chosen']);
        Challenge::factory()->system()->easy()->create(['name' => 'Not chosen']);

        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $chosen->id,
        ]);

        $names = collect(
            $this->actingAs($this->attendee())
                ->getJson("/api/v1/events/{$event->id}/challenges")
                ->json('data.challenges')
        )->pluck('name');

        $this->assertContains('Chosen', $names);
        $this->assertNotContains('Not chosen', $names);
    }

    /**
     * The event's own challenges are the leader's and are always in, whatever
     * the community has curated.
     */
    public function test_the_events_own_challenges_survive_curation(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community);

        $chosen = Challenge::factory()->system()->easy()->create(['name' => 'Chosen']);
        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $chosen->id,
        ]);

        Challenge::factory()->easy()->create([
            'name' => 'Just for tonight',
            'is_system' => false,
            'event_id' => $event->id,
        ]);

        $names = collect(
            $this->actingAs($this->attendee())
                ->getJson("/api/v1/events/{$event->id}/challenges")
                ->json('data.challenges')
        )->pluck('name');

        $this->assertContains('Just for tonight', $names);
        $this->assertContains('Chosen', $names);
    }

    public function test_one_communitys_choice_does_not_affect_another(): void
    {
        $leaderA = $this->leader();
        $leaderB = $this->leader();
        $communityA = $this->community($leaderA);
        $communityB = $this->community($leaderB);

        $chosen = Challenge::factory()->system()->easy()->create(['name' => 'Only A picked this']);
        Challenge::factory()->system()->easy()->create(['name' => 'Nobody picked this']);

        CommunityChallenge::query()->create([
            'community_id' => $communityA->id,
            'challenge_id' => $chosen->id,
        ]);

        $eventB = $this->event($leaderB, $communityB);

        $names = collect(
            $this->actingAs($this->attendee())
                ->getJson("/api/v1/events/{$eventB->id}/challenges")
                ->json('data.challenges')
        )->pluck('name');

        // B curated nothing, so B still gets everything.
        $this->assertContains('Nobody picked this', $names);
        $this->assertContains('Only A picked this', $names);
    }

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    public function test_a_leader_can_curate(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $challenge = Challenge::factory()->system()->easy()->create();

        $this->actingAs($leader)
            ->putJson("/api/v1/communities/{$community->id}/challenges", [
                'challenges' => [
                    [
                        'challenge_id' => $challenge->id,
                        'allow_repeat_with_same_person' => true,
                        'requires_new_person' => false,
                    ],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.curated', true)
            ->assertJsonPath('data.challenges.0.allow_repeat_with_same_person', true);
    }

    /**
     * The only way back to the default, which is why an empty array is a valid
     * request rather than a validation error.
     */
    public function test_syncing_an_empty_array_turns_curation_off(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $challenge = Challenge::factory()->system()->easy()->create();
        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $challenge->id,
        ]);

        $this->actingAs($leader)
            ->putJson("/api/v1/communities/{$community->id}/challenges", ['challenges' => []])
            ->assertSuccessful()
            ->assertJsonPath('data.curated', false);

        $this->assertSame(0, CommunityChallenge::query()->where('community_id', $community->id)->count());
    }

    public function test_a_stranger_cannot_curate(): void
    {
        $community = $this->community($this->leader());
        $challenge = Challenge::factory()->system()->easy()->create();

        $this->actingAs($this->attendee())
            ->putJson("/api/v1/communities/{$community->id}/challenges", [
                'challenges' => [['challenge_id' => $challenge->id]],
            ])
            ->assertStatus(403);
    }

    public function test_the_library_lists_only_playable_system_challenges(): void
    {
        Challenge::factory()->system()->easy()->create(['name' => 'Playable']);
        Challenge::factory()->easy()->create(['name' => 'Someone elses event', 'is_system' => false]);

        $names = collect(
            $this->actingAs($this->attendee())
                ->getJson('/api/v1/challenge-library')
                ->json('data.challenges')
        )->pluck('name');

        $this->assertContains('Playable', $names);
        $this->assertNotContains('Someone elses event', $names);
    }

    /*
    |--------------------------------------------------------------------------
    | §6 repeating, §7 a new person
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{event: Event, a: Profile, b: Profile, challenge: Challenge, community: Community}
     */
    private function pairScenario(): array
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community);
        $challenge = Challenge::factory()->system()->easy()->create();

        $a = $this->attendee();
        $b = $this->attendee();
        EventCheckin::factory()->forEvent($event)->forProfile($a)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($b)->create();

        return compact('event', 'a', 'b', 'challenge', 'community');
    }

    private function initiate(Profile $a, Event $event, Challenge $challenge, Profile $b): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($a)->postJson('/api/v1/challenges/initiate', [
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'verifier_profile_id' => $b->id,
        ]);
    }

    public function test_a_pair_cannot_repeat_by_default(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'challenge' => $challenge] = $this->pairScenario();

        $id = $this->initiate($a, $event, $challenge, $b)->assertSuccessful()->json('data.id');
        $this->actingAs($b)->postJson("/api/v1/challenge-completions/{$id}/verify")->assertSuccessful();

        $this->initiate($a, $event, $challenge, $b)
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_completed');
    }

    public function test_a_pair_can_repeat_when_their_community_allows_it(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'challenge' => $challenge, 'community' => $community] =
            $this->pairScenario();

        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $challenge->id,
            'allow_repeat_with_same_person' => true,
        ]);

        $id = $this->initiate($a, $event, $challenge, $b)->assertSuccessful()->json('data.id');
        $this->actingAs($b)->postJson("/api/v1/challenge-completions/{$id}/verify")->assertSuccessful();

        $this->initiate($a, $event, $challenge, $b)->assertSuccessful();
    }

    /**
     * Repeating being allowed does not mean two live requests for the same
     * thing. That is never what anyone meant (§19).
     */
    public function test_two_pending_requests_are_refused_even_when_repeats_are_allowed(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'challenge' => $challenge, 'community' => $community] =
            $this->pairScenario();

        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $challenge->id,
            'allow_repeat_with_same_person' => true,
        ]);

        $this->initiate($a, $event, $challenge, $b)->assertSuccessful();

        // A distinct reason, because "you already asked them" and "you two have
        // already done this" want different words on screen.
        $this->initiate($a, $event, $challenge, $b)
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_pending');
    }

    public function test_requires_new_person_refuses_a_pair_who_have_played_before(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'challenge' => $challenge, 'community' => $community] =
            $this->pairScenario();

        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $challenge->id,
            'requires_new_person' => true,
        ]);

        // They have met before — and note the direction is REVERSED, because
        // "we already met" is symmetric.
        ChallengeCompletion::query()->create([
            'challenge_id' => Challenge::factory()->system()->easy()->create()->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $b->id,
            'verifier_profile_id' => $a->id,
            'status' => ChallengeCompletionStatus::Verified->value,
            'points_earned' => 5,
        ]);

        $this->initiate($a, $event, $challenge, $b)
            ->assertStatus(409)
            ->assertJsonPath('error', 'needs_new_person');
    }

    public function test_requires_new_person_allows_a_pair_who_have_not(): void
    {
        ['event' => $event, 'a' => $a, 'b' => $b, 'challenge' => $challenge, 'community' => $community] =
            $this->pairScenario();

        CommunityChallenge::query()->create([
            'community_id' => $community->id,
            'challenge_id' => $challenge->id,
            'requires_new_person' => true,
        ]);

        $this->initiate($a, $event, $challenge, $b)->assertSuccessful();
    }
}
