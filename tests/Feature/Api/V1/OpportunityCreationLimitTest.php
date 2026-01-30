<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityCreationLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function validOpportunityData(): array
    {
        return [
            'title' => 'Test Opportunity',
            'description' => 'A test opportunity for collaboration.',
            'business_offer' => ['venue' => true, 'food_drink' => false],
            'community_deliverables' => ['instagram_post' => true, 'attendee_count' => 50],
            'categories' => ['Food & Drink'],
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'venue_mode' => 'business_venue',
            'address' => 'Calle Test 123, Sevilla',
            'preferred_city' => 'Sevilla',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Free Limit for Business Users Without Subscription
    |--------------------------------------------------------------------------
    */

    public function test_business_user_without_subscription_can_create_up_to_3_opportunities(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($business)
                ->postJson('/api/v1/opportunities', $this->validOpportunityData());

            $response->assertStatus(201)
                ->assertJsonPath('success', true);
        }

        $this->assertDatabaseCount('collab_opportunities', 3);
    }

    public function test_business_user_without_subscription_cannot_create_4th_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        // Create 3 opportunities directly via factory
        CollabOpportunity::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('message', 'You have reached the free opportunity limit. Please subscribe to create more opportunities.');
    }

    public function test_business_user_with_active_subscription_can_create_unlimited_opportunities(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        // Create 3 opportunities first
        CollabOpportunity::factory()->count(3)->forCreator($business)->create();

        // 4th should succeed because subscription is active
        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_community_user_can_create_unlimited_opportunities_without_subscription(): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        // Create 4 opportunities directly
        CollabOpportunity::factory()->count(4)->forCreator($community)->create();

        // 5th should also succeed since limit doesn't apply to community users
        $response = $this->actingAs($community)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_limit_counts_all_opportunity_statuses(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        // Create opportunities in different statuses
        CollabOpportunity::factory()->forCreator($business)->create(); // draft
        CollabOpportunity::factory()->published()->forCreator($business)->create();
        CollabOpportunity::factory()->closed()->forCreator($business)->create();

        // 4th should fail since all statuses count toward the limit
        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_user_with_cancelled_subscription_is_limited(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->cancelled()->create(['profile_id' => $business->id]);

        CollabOpportunity::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_user_with_past_due_subscription_is_limited(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->pastDue()->create(['profile_id' => $business->id]);

        CollabOpportunity::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Service Unit Tests
    |--------------------------------------------------------------------------
    */

    public function test_has_reached_free_limit_returns_false_for_community_user(): void
    {
        $community = Profile::factory()->community()->create();
        CollabOpportunity::factory()->count(5)->forCreator($community)->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreeLimit($community));
    }

    public function test_has_reached_free_limit_returns_false_for_subscribed_business(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        CollabOpportunity::factory()->count(5)->forCreator($business)->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreeLimit($business));
    }

    public function test_has_reached_free_limit_returns_true_at_limit(): void
    {
        $business = Profile::factory()->business()->create();
        CollabOpportunity::factory()->count(3)->forCreator($business)->create();

        $service = new OpportunityService;
        $this->assertTrue($service->hasReachedFreeLimit($business));
    }

    public function test_has_reached_free_limit_returns_false_below_limit(): void
    {
        $business = Profile::factory()->business()->create();
        CollabOpportunity::factory()->count(2)->forCreator($business)->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreeLimit($business));
    }
}
