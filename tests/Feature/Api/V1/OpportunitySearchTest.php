<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunitySearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Search by Opportunity Title
    |--------------------------------------------------------------------------
    */

    public function test_search_finds_opportunities_by_title(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'Yoga Workshop Partnership']);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'Coffee Tasting Event']);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=yoga');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Yoga Workshop Partnership');
    }

    public function test_search_is_case_insensitive(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'YOGA Workshop']);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=yoga');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Search by Opportunity Description
    |--------------------------------------------------------------------------
    */

    public function test_search_finds_opportunities_by_description(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create([
                'title' => 'Generic Event',
                'description' => 'Join us for an amazing meditation session',
            ]);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create([
                'title' => 'Another Event',
                'description' => 'Sports and fitness activities',
            ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=meditation');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Search by Community Creator Name
    |--------------------------------------------------------------------------
    */

    public function test_search_finds_opportunities_by_community_creator_name(): void
    {
        $viewer = Profile::factory()->business()->create();

        // Community creator with specific name
        $communityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($communityCreator, 'profile')->create([
            'name' => 'Barcelona Runners Club',
        ]);

        // Another community creator with different name
        $anotherCommunityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($anotherCommunityCreator, 'profile')->create([
            'name' => 'Madrid Yoga Studio',
        ]);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'Morning Run Event']);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($anotherCommunityCreator)
            ->create(['title' => 'Yoga Class']);

        // Search by community name
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=Barcelona');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Morning Run Event');
    }

    public function test_search_finds_opportunities_by_partial_community_name(): void
    {
        $viewer = Profile::factory()->business()->create();

        $communityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($communityCreator, 'profile')->create([
            'name' => 'Barcelona Running Community',
        ]);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'Weekly Run']);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=running');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Search by Business Creator Name
    |--------------------------------------------------------------------------
    */

    public function test_search_finds_opportunities_by_business_creator_name(): void
    {
        $viewer = Profile::factory()->community()->create();

        // Business creator with specific name
        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->for($businessCreator, 'profile')->create([
            'name' => 'Organic Coffee House',
        ]);

        // Another business creator with different name
        $anotherBusinessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->for($anotherBusinessCreator, 'profile')->create([
            'name' => 'Tech Startup Hub',
        ]);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create(['title' => 'Coffee Tasting Partnership']);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($anotherBusinessCreator)
            ->create(['title' => 'Tech Workshop']);

        // Search by business name
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=Organic');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Coffee Tasting Partnership');
    }

    /*
    |--------------------------------------------------------------------------
    | Combined Search Scenarios
    |--------------------------------------------------------------------------
    */

    public function test_search_matches_across_title_description_and_creator_name(): void
    {
        $viewer = Profile::factory()->business()->create();

        // Opportunity with matching title
        $creator1 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator1, 'profile')->create(['name' => 'Group A']);
        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator1)
            ->create(['title' => 'Fitness Workshop', 'description' => 'Generic description']);

        // Opportunity with matching description
        $creator2 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator2, 'profile')->create(['name' => 'Group B']);
        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator2)
            ->create(['title' => 'Generic Event', 'description' => 'A fitness related activity']);

        // Opportunity with matching creator name
        $creator3 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator3, 'profile')->create(['name' => 'Fitness Club BCN']);
        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator3)
            ->create(['title' => 'Morning Run', 'description' => 'Join our run']);

        // Opportunity with no match
        $creator4 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator4, 'profile')->create(['name' => 'Art Gallery']);
        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator4)
            ->create(['title' => 'Art Exhibition', 'description' => 'Modern art show']);

        // Search for "fitness" should find 3 opportunities
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=fitness');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_search_with_no_results_returns_empty(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()
            ->published()
            ->forCreator($communityCreator)
            ->create(['title' => 'Yoga Workshop']);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=nonexistentterm');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_search_combined_with_other_filters(): void
    {
        $viewer = Profile::factory()->business()->create();

        $creator1 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator1, 'profile')->create(['name' => 'Yoga Masters']);

        $creator2 = Profile::factory()->community()->create();
        CommunityProfile::factory()->for($creator2, 'profile')->create(['name' => 'Yoga Beginners']);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator1)
            ->create([
                'title' => 'Morning Session',
                'preferred_city' => 'Barcelona',
            ]);

        CollabOpportunity::factory()
            ->published()
            ->forCreator($creator2)
            ->create([
                'title' => 'Evening Session',
                'preferred_city' => 'Madrid',
            ]);

        // Search for "yoga" in Barcelona only
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=yoga&city=Barcelona');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Morning Session');
    }

    public function test_empty_search_returns_all_matching_opportunities(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()->count(3)
            ->published()
            ->forCreator($communityCreator)
            ->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities?search=');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }
}
