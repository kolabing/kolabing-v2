<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessSubscription;
use App\Models\Collaboration;
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
            'selected_time' => null,
            'recurring_days' => null,
            'venue_mode' => 'business_venue',
            'address' => 'Calle Test 123, Sevilla',
            'preferred_city' => 'Sevilla',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Freemium Gate: 1 free collaboration, then subscription required
    |--------------------------------------------------------------------------
    */

    public function test_business_without_subscription_and_no_collabs_can_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_business_without_subscription_and_one_collab_cannot_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_without_subscription_and_multiple_collabs_cannot_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();

        Collaboration::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_active_subscription_and_collabs_can_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        Collaboration::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_community_user_can_create_opportunities_regardless_of_collabs(): void
    {
        $community = Profile::factory()->community()->create();

        // Community profiles are always the applicant side in collaborations; creator_profile_id is always a business
        Collaboration::factory()->count(5)->forApplicant($community)->create();

        $response = $this->actingAs($community)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_all_collab_statuses_count_toward_limit(): void
    {
        $business = Profile::factory()->business()->create();

        // One cancelled collaboration still triggers the gate
        Collaboration::factory()->cancelled()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_cancelled_subscription_and_collab_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->cancelled()->create(['profile_id' => $business->id]);

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_past_due_subscription_and_collab_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->pastDue()->create(['profile_id' => $business->id]);

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Service Unit Tests
    |--------------------------------------------------------------------------
    */

    public function test_has_reached_freemium_collab_limit_returns_false_for_community_user(): void
    {
        $community = Profile::factory()->community()->create();
        Collaboration::factory()->count(5)->forApplicant($community)->create();

        $service = app(OpportunityService::class);
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($community));
    }

    public function test_has_reached_freemium_collab_limit_returns_false_for_subscribed_business(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        Collaboration::factory()->count(5)->forCreator($business)->create();

        $service = app(OpportunityService::class);
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($business));
    }

    public function test_has_reached_freemium_collab_limit_returns_true_with_one_collab(): void
    {
        $business = Profile::factory()->business()->create();
        Collaboration::factory()->forCreator($business)->create();

        $service = app(OpportunityService::class);
        $this->assertTrue($service->hasReachedFreemiumCollabLimit($business));
    }

    public function test_has_reached_freemium_collab_limit_returns_false_with_no_collabs(): void
    {
        $business = Profile::factory()->business()->create();

        $service = app(OpportunityService::class);
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($business));
    }
}
