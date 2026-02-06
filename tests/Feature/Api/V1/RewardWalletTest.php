<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\RewardClaimStatus;
use App\Models\BusinessProfile;
use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RewardWalletTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | My Rewards (Wallet)
    |--------------------------------------------------------------------------
    */

    public function test_my_rewards_returns_paginated_claims(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        RewardClaim::factory()->count(3)->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/rewards');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.rewards')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rewards' => [
                        '*' => ['id', 'event_reward', 'profile_id', 'status', 'won_at', 'redeemed_at', 'redeem_token', 'created_at'],
                    ],
                    'pagination' => ['current_page', 'total_pages', 'total_count', 'per_page'],
                ],
            ]);
    }

    public function test_my_rewards_returns_empty_when_no_claims(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/rewards');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.rewards')
            ->assertJsonPath('data.pagination.total_count', 0);
    }

    public function test_my_rewards_respects_limit_parameter(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        RewardClaim::factory()->count(5)->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/rewards?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.rewards')
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total_count', 5);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Redeem QR
    |--------------------------------------------------------------------------
    */

    public function test_generate_redeem_qr_sets_token(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->create();
        $claim = RewardClaim::factory()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/reward-claims/{$claim->id}/generate-redeem-qr");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $claim->refresh();
        $this->assertNotNull($claim->redeem_token);
        $this->assertEquals(64, strlen($claim->redeem_token));
    }

    public function test_cannot_generate_qr_for_other_users_claim(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $otherAttendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->create();
        $claim = RewardClaim::factory()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $otherAttendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/reward-claims/{$claim->id}/generate-redeem-qr");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_generate_qr_for_redeemed_claim(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->create();
        $claim = RewardClaim::factory()->redeemed()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/reward-claims/{$claim->id}/generate-redeem-qr");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_generate_qr_for_expired_reward(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = Event::factory()->create();
        $reward = EventReward::factory()->forEvent($event)->expired()->create();
        $claim = RewardClaim::factory()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/reward-claims/{$claim->id}/generate-redeem-qr");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $claim->refresh();
        $this->assertEquals(RewardClaimStatus::Expired, $claim->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Redeem
    |--------------------------------------------------------------------------
    */

    public function test_confirm_redeem_sets_status_to_redeemed(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $attendee = Profile::factory()->attendee()->create();
        $claim = RewardClaim::factory()->withRedeemToken()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);
        $token = $claim->redeem_token;

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/reward-claims/confirm-redeem', [
                'token' => $token,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reward redeemed successfully.');

        $claim->refresh();
        $this->assertEquals(RewardClaimStatus::Redeemed, $claim->status);
        $this->assertNotNull($claim->redeemed_at);
        $this->assertNull($claim->redeem_token);
    }

    public function test_confirm_redeem_fails_with_invalid_token(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/reward-claims/confirm-redeem', [
                'token' => str_repeat('x', 64),
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid redeem token.');
    }

    public function test_confirm_redeem_fails_when_not_event_owner(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $attendee = Profile::factory()->attendee()->create();
        $claim = RewardClaim::factory()->withRedeemToken()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $otherUser = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $otherUser->id]);

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/reward-claims/confirm-redeem', [
                'token' => $claim->redeem_token,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_confirm_redeem_for_already_redeemed_claim(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        $attendee = Profile::factory()->attendee()->create();
        $claim = RewardClaim::factory()->redeemed()->withRedeemToken()->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/reward-claims/confirm-redeem', [
                'token' => $claim->redeem_token,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_returns_401_for_my_rewards(): void
    {
        $this->getJson('/api/v1/me/rewards')->assertStatus(401);
    }

    public function test_unauthenticated_returns_401_for_confirm_redeem(): void
    {
        $this->postJson('/api/v1/reward-claims/confirm-redeem', [
            'token' => str_repeat('x', 64),
        ])->assertStatus(401);
    }
}
