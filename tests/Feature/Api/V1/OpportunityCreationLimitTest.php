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

    public function test_legacy_opportunity_create_endpoint_writes_only_kolab(): void
    {
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Test Opportunity');

        $this->assertDatabaseCount('kolabs', 1);
        $this->assertDatabaseCount('collab_opportunities', 0);
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
