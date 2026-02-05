<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChallengeCompletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Set up a full scenario with two checked-in attendees and a challenge.
     *
     * @return array{event: Event, challenger: Profile, verifier: Profile, challenge: Challenge, owner: Profile}
     */
    private function setupCheckedInPair(): array
    {
        // Create event with a business owner
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token',
            'max_challenges_per_attendee' => 5,
        ]);

        // Create two attendees
        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        // Check both in
        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        // Create a system challenge
        $challenge = Challenge::factory()->system()->easy()->create();

        return compact('event', 'challenger', 'verifier', 'challenge', 'owner');
    }

    /*
    |--------------------------------------------------------------------------
    | Initiate Challenge (POST /api/v1/challenges/initiate)
    |--------------------------------------------------------------------------
    */

    public function test_can_initiate_challenge(): void
    {
        $setup = $this->setupCheckedInPair();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $setup['challenge']->id,
                'event_id' => $setup['event']->id,
                'verifier_profile_id' => $setup['verifier']->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.challenger_profile_id', $setup['challenger']->id)
            ->assertJsonPath('data.verifier_profile_id', $setup['verifier']->id)
            ->assertJsonPath('data.event_id', $setup['event']->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.points_earned', 0)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'challenge',
                    'event_id',
                    'challenger_profile_id',
                    'verifier_profile_id',
                    'status',
                    'points_earned',
                    'completed_at',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('challenge_completions', [
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending->value,
        ]);
    }

    public function test_cannot_initiate_if_not_checked_in(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token',
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        // Only verifier is checked in, not challenger
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create();

        $response = $this->actingAs($challenger)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $challenge->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $verifier->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_initiate_if_verifier_not_checked_in(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token',
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        // Only challenger is checked in, not verifier
        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();

        $challenge = Challenge::factory()->system()->easy()->create();

        $response = $this->actingAs($challenger)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $challenge->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $verifier->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_duplicate_challenge_same_pair(): void
    {
        $setup = $this->setupCheckedInPair();

        // First initiation should succeed
        $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $setup['challenge']->id,
                'event_id' => $setup['event']->id,
                'verifier_profile_id' => $setup['verifier']->id,
            ])
            ->assertStatus(201);

        // Duplicate should fail with 409
        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $setup['challenge']->id,
                'event_id' => $setup['event']->id,
                'verifier_profile_id' => $setup['verifier']->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_exceed_max_challenges(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token',
            'max_challenges_per_attendee' => 1,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier1 = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier1->id]);
        $verifier2 = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier2->id]);

        // Check everyone in
        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier1)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier2)->create();

        $challenge1 = Challenge::factory()->system()->easy()->create();
        $challenge2 = Challenge::factory()->system()->medium()->create();

        // First challenge should succeed
        $this->actingAs($challenger)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $challenge1->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $verifier1->id,
            ])
            ->assertStatus(201);

        // Second challenge should fail with 409 (max exceeded)
        $response = $this->actingAs($challenger)
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $challenge2->id,
                'event_id' => $event->id,
                'verifier_profile_id' => $verifier2->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_challenge_yourself(): void
    {
        $setup = $this->setupCheckedInPair();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/challenges/initiate', [
                'challenge_id' => $setup['challenge']->id,
                'event_id' => $setup['event']->id,
                'verifier_profile_id' => $setup['challenger']->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['verifier_profile_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Challenge (POST /api/v1/challenge-completions/{id}/verify)
    |--------------------------------------------------------------------------
    */

    public function test_verifier_can_verify(): void
    {
        $setup = $this->setupCheckedInPair();

        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $response = $this->actingAs($setup['verifier'])
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.points_earned', $setup['challenge']->points);

        $this->assertDatabaseHas('challenge_completions', [
            'id' => $completion->id,
            'status' => ChallengeCompletionStatus::Verified->value,
            'points_earned' => $setup['challenge']->points,
        ]);

        // Verify completed_at is set
        $completion->refresh();
        $this->assertNotNull($completion->completed_at);
    }

    public function test_verifier_can_reject(): void
    {
        $setup = $this->setupCheckedInPair();

        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $response = $this->actingAs($setup['verifier'])
            ->postJson("/api/v1/challenge-completions/{$completion->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.points_earned', 0);

        $this->assertDatabaseHas('challenge_completions', [
            'id' => $completion->id,
            'status' => ChallengeCompletionStatus::Rejected->value,
            'points_earned' => 0,
        ]);

        // Verify points not added to attendee profile
        $setup['challenger']->refresh();
        $this->assertEquals(0, $setup['challenger']->attendeeProfile->total_points);
    }

    public function test_non_verifier_cannot_verify(): void
    {
        $setup = $this->setupCheckedInPair();

        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending,
        ]);

        // The challenger (not the verifier) tries to verify
        $response = $this->actingAs($setup['challenger'])
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_verify_already_verified(): void
    {
        $setup = $this->setupCheckedInPair();

        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Verified,
            'completed_at' => now(),
            'points_earned' => $setup['challenge']->points,
        ]);

        $response = $this->actingAs($setup['verifier'])
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_points_added_to_attendee_profile(): void
    {
        $setup = $this->setupCheckedInPair();

        // Verify initial state
        $attendeeProfile = $setup['challenger']->attendeeProfile;
        $this->assertEquals(0, $attendeeProfile->total_points);
        $this->assertEquals(0, $attendeeProfile->total_challenges_completed);

        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $setup['challenge']->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $this->actingAs($setup['verifier'])
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify")
            ->assertStatus(200);

        // Refresh and check
        $attendeeProfile->refresh();
        $this->assertEquals($setup['challenge']->points, $attendeeProfile->total_points);
        $this->assertEquals(1, $attendeeProfile->total_challenges_completed);
    }

    /*
    |--------------------------------------------------------------------------
    | My Completions (GET /api/v1/me/challenge-completions)
    |--------------------------------------------------------------------------
    */

    public function test_my_completions_returns_history(): void
    {
        $setup = $this->setupCheckedInPair();

        // Create completions where challenger is involved (each with a different challenge to avoid unique constraint)
        for ($i = 0; $i < 3; $i++) {
            $challenge = Challenge::factory()->system()->create();
            ChallengeCompletion::factory()->create([
                'challenge_id' => $challenge->id,
                'event_id' => $setup['event']->id,
                'challenger_profile_id' => $setup['challenger']->id,
                'verifier_profile_id' => $setup['verifier']->id,
            ]);
        }

        // Create a completion where challenger is the verifier
        $otherChallenger = Profile::factory()->attendee()->create();
        ChallengeCompletion::factory()->create([
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $otherChallenger->id,
            'verifier_profile_id' => $setup['challenger']->id,
        ]);

        // Create unrelated completions (should not appear)
        ChallengeCompletion::factory()->count(2)->create();

        $response = $this->actingAs($setup['challenger'])
            ->getJson('/api/v1/me/challenge-completions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.completions')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'completions' => [
                        '*' => [
                            'id',
                            'challenge',
                            'event_id',
                            'challenger_profile_id',
                            'verifier_profile_id',
                            'status',
                            'points_earned',
                            'completed_at',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'total_pages',
                        'total_count',
                        'per_page',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/challenges/initiate', [
            'challenge_id' => fake()->uuid(),
            'event_id' => fake()->uuid(),
            'verifier_profile_id' => fake()->uuid(),
        ])->assertStatus(401);

        $this->postJson('/api/v1/challenge-completions/'.fake()->uuid().'/verify')
            ->assertStatus(401);

        $this->postJson('/api/v1/challenge-completions/'.fake()->uuid().'/reject')
            ->assertStatus(401);

        $this->getJson('/api/v1/me/challenge-completions')
            ->assertStatus(401);
    }
}
