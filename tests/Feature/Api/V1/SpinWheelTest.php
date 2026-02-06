<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\RewardClaimStatus;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SpinWheelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Set up a full scenario with two checked-in attendees, a verified challenge completion,
     * and an event reward ready for spinning.
     *
     * @return array{event: Event, challenger: Profile, verifier: Profile, challenge: Challenge, completion: ChallengeCompletion, owner: Profile}
     */
    private function setupVerifiedCompletion(): array
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token',
            'max_challenges_per_attendee' => 5,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create();

        $completion = ChallengeCompletion::factory()->verified()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'points_earned' => $challenge->points,
        ]);

        return compact('event', 'challenger', 'verifier', 'challenge', 'completion', 'owner');
    }

    /*
    |--------------------------------------------------------------------------
    | Spin the Wheel - Happy Paths
    |--------------------------------------------------------------------------
    */

    public function test_spin_wins_reward_with_high_probability(): void
    {
        $setup = $this->setupVerifiedCompletion();

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.won', true)
            ->assertJsonPath('message', 'Congratulations! You won a reward!')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'won',
                    'reward_claim' => [
                        'id',
                        'event_reward',
                        'profile_id',
                        'status',
                        'won_at',
                        'redeemed_at',
                        'redeem_token',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_spin_creates_reward_claim_with_correct_data(): void
    {
        $setup = $this->setupVerifiedCompletion();

        $reward = EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create();

        $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.won', true);

        $this->assertDatabaseHas('reward_claims', [
            'event_reward_id' => $reward->id,
            'profile_id' => $setup['challenger']->id,
            'challenge_completion_id' => $setup['completion']->id,
            'status' => RewardClaimStatus::Available->value,
        ]);

        $claim = RewardClaim::query()->where('challenge_completion_id', $setup['completion']->id)->first();
        $this->assertNotNull($claim);
        $this->assertNotNull($claim->won_at);
    }

    public function test_spin_decrements_remaining_quantity(): void
    {
        $setup = $this->setupVerifiedCompletion();

        $reward = EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create(['total_quantity' => 10, 'remaining_quantity' => 10]);

        $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.won', true);

        $reward->refresh();
        $this->assertEquals(9, $reward->remaining_quantity);
    }

    /*
    |--------------------------------------------------------------------------
    | Spin the Wheel - Validation / Auth Failures
    |--------------------------------------------------------------------------
    */

    public function test_cannot_spin_for_pending_completion(): void
    {
        $setup = $this->setupVerifiedCompletion();

        $anotherChallenge = Challenge::factory()->system()->medium()->create();
        $pendingCompletion = ChallengeCompletion::factory()->create([
            'challenge_id' => $anotherChallenge->id,
            'event_id' => $setup['event']->id,
            'challenger_profile_id' => $setup['challenger']->id,
            'verifier_profile_id' => $setup['verifier']->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $pendingCompletion->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Challenge completion must be verified before spinning.');
    }

    public function test_cannot_spin_if_not_the_challenger(): void
    {
        $setup = $this->setupVerifiedCompletion();

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create();

        $response = $this->actingAs($setup['verifier'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You are not the challenger for this completion.');
    }

    public function test_cannot_spin_twice_for_same_completion(): void
    {
        $setup = $this->setupVerifiedCompletion();

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->create();

        // First spin
        $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.won', true);

        // Second spin
        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You have already spun for this challenge completion.');
    }

    /*
    |--------------------------------------------------------------------------
    | Spin the Wheel - No Win Scenarios
    |--------------------------------------------------------------------------
    */

    public function test_spin_returns_not_won_when_no_rewards_exist(): void
    {
        $setup = $this->setupVerifiedCompletion();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.won', false)
            ->assertJsonPath('data.reward_claim', null)
            ->assertJsonPath('message', 'Better luck next time!');
    }

    public function test_spin_returns_not_won_when_all_rewards_out_of_stock(): void
    {
        $setup = $this->setupVerifiedCompletion();

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->outOfStock()
            ->create();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.won', false)
            ->assertJsonPath('data.reward_claim', null);
    }

    public function test_spin_returns_not_won_when_all_rewards_expired(): void
    {
        $setup = $this->setupVerifiedCompletion();

        EventReward::factory()
            ->forEvent($setup['event'])
            ->highProbability(1.0)
            ->expired()
            ->create();

        $response = $this->actingAs($setup['challenger'])
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $setup['completion']->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.won', false)
            ->assertJsonPath('data.reward_claim', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/rewards/spin', [
            'challenge_completion_id' => fake()->uuid(),
        ])->assertStatus(401);
    }
}
