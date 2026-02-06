<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EventRewardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Create a business profile with its extended profile record.
     */
    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * Create a community profile with its extended profile record.
     */
    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | List Rewards (GET /api/v1/events/{event}/rewards)
    |--------------------------------------------------------------------------
    */

    public function test_list_rewards_for_event_returns_all_rewards(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        EventReward::factory()->forEvent($event)->count(3)->create();

        // Create rewards for another event (should NOT appear)
        $otherEvent = Event::factory()->create();
        EventReward::factory()->forEvent($otherEvent)->count(2)->create();

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/events/{$event->id}/rewards");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'event_id',
                        'name',
                        'description',
                        'total_quantity',
                        'remaining_quantity',
                        'probability',
                        'expires_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Reward (POST /api/v1/events/{event}/rewards)
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_create_reward_with_valid_data(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/rewards", [
                'name' => 'Free Coffee Voucher',
                'description' => 'Enjoy a free coffee on us!',
                'total_quantity' => 50,
                'probability' => 0.25,
                'expires_at' => now()->addMonth()->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Free Coffee Voucher')
            ->assertJsonPath('data.description', 'Enjoy a free coffee on us!')
            ->assertJsonPath('data.total_quantity', 50)
            ->assertJsonPath('data.remaining_quantity', 50)
            ->assertJsonPath('data.probability', 0.25)
            ->assertJsonPath('data.event_id', $event->id);

        $this->assertDatabaseHas('event_rewards', [
            'name' => 'Free Coffee Voucher',
            'event_id' => $event->id,
            'total_quantity' => 50,
            'remaining_quantity' => 50,
        ]);
    }

    public function test_non_owner_cannot_create_reward_for_event(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($nonOwner)
            ->postJson("/api/v1/events/{$event->id}/rewards", [
                'name' => 'Unauthorized Reward',
                'total_quantity' => 10,
                'probability' => 0.5,
            ]);

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Reward (PUT /api/v1/event-rewards/{eventReward})
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_update_reward_name_quantity_probability(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create([
            'name' => 'Original Reward',
            'total_quantity' => 100,
            'remaining_quantity' => 100,
            'probability' => 0.5,
        ]);

        $response = $this->actingAs($owner)
            ->putJson("/api/v1/event-rewards/{$reward->id}", [
                'name' => 'Updated Reward Name',
                'probability' => 0.75,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Reward Name')
            ->assertJsonPath('data.probability', 0.75)
            ->assertJsonPath('data.total_quantity', 100)
            ->assertJsonPath('data.remaining_quantity', 100);

        $this->assertDatabaseHas('event_rewards', [
            'id' => $reward->id,
            'name' => 'Updated Reward Name',
        ]);
    }

    public function test_updating_total_quantity_adjusts_remaining_quantity_proportionally(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create([
            'total_quantity' => 100,
            'remaining_quantity' => 80,
        ]);

        // Increase total by 20 -> remaining should increase by 20 (80 + 20 = 100)
        $response = $this->actingAs($owner)
            ->putJson("/api/v1/event-rewards/{$reward->id}", [
                'total_quantity' => 120,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_quantity', 120)
            ->assertJsonPath('data.remaining_quantity', 100);

        // Decrease total by a lot -> remaining should be clamped to 0
        $reward->refresh();
        $response = $this->actingAs($owner)
            ->putJson("/api/v1/event-rewards/{$reward->id}", [
                'total_quantity' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_quantity', 10)
            ->assertJsonPath('data.remaining_quantity', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Reward (DELETE /api/v1/event-rewards/{eventReward})
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_delete_reward_with_no_claims(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/event-rewards/{$reward->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('event_rewards', ['id' => $reward->id]);
    }

    public function test_cannot_delete_reward_that_has_existing_claims(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        // Create a claim for this reward
        RewardClaim::factory()->create([
            'event_reward_id' => $reward->id,
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/event-rewards/{$reward->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('event_rewards', ['id' => $reward->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_non_owner_cannot_update_reward(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $response = $this->actingAs($nonOwner)
            ->putJson("/api/v1/event-rewards/{$reward->id}", [
                'name' => 'Hacked Reward',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_owner_cannot_delete_reward(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $response = $this->actingAs($nonOwner)
            ->deleteJson("/api/v1/event-rewards/{$reward->id}");

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_validation_errors_for_invalid_input(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        // Missing required fields
        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/rewards", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'total_quantity', 'probability']);

        // Probability out of range
        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/rewards", [
                'name' => 'Test Reward',
                'total_quantity' => 10,
                'probability' => 1.5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['probability']);

        // Name too short
        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/rewards", [
                'name' => 'A',
                'total_quantity' => 10,
                'probability' => 0.5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_gets_401(): void
    {
        $event = Event::factory()->create();

        $this->getJson("/api/v1/events/{$event->id}/rewards")
            ->assertStatus(401);

        $this->postJson("/api/v1/events/{$event->id}/rewards", [
            'name' => 'Test',
            'total_quantity' => 10,
            'probability' => 0.5,
        ])->assertStatus(401);

        $reward = EventReward::factory()->forEvent($event)->create();

        $this->putJson("/api/v1/event-rewards/{$reward->id}", [
            'name' => 'Test',
        ])->assertStatus(401);

        $this->deleteJson("/api/v1/event-rewards/{$reward->id}")
            ->assertStatus(401);
    }
}
