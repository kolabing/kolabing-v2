<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | My Opportunities (GET /api/v1/me/opportunities)
    |--------------------------------------------------------------------------
    */

    public function test_my_opportunities_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/opportunities');

        $response->assertStatus(401);
    }

    public function test_my_opportunities_returns_only_own_opportunities(): void
    {
        $owner = Profile::factory()->business()->create();
        $other = Profile::factory()->business()->create();

        CollabOpportunity::factory()->count(3)->published()->forCreator($owner)->create();
        CollabOpportunity::factory()->count(2)->published()->forCreator($other)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/me/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_opportunities_returns_all_statuses(): void
    {
        $owner = Profile::factory()->business()->create();

        CollabOpportunity::factory()->forCreator($owner)->create(); // draft
        CollabOpportunity::factory()->published()->forCreator($owner)->create();
        CollabOpportunity::factory()->closed()->forCreator($owner)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/me/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_opportunities_filters_by_status(): void
    {
        $owner = Profile::factory()->business()->create();

        CollabOpportunity::factory()->forCreator($owner)->create(); // draft
        CollabOpportunity::factory()->count(2)->published()->forCreator($owner)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/me/opportunities?status=published');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_my_opportunities_returns_correct_structure(): void
    {
        $owner = Profile::factory()->business()->create();
        CollabOpportunity::factory()->published()->forCreator($owner)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/me/opportunities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'status',
                            'business_offer',
                            'community_deliverables',
                            'categories',
                            'availability_mode',
                            'availability_start',
                            'availability_end',
                            'venue_mode',
                            'preferred_city',
                            'is_own',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_my_opportunities_returns_empty_when_none_exist(): void
    {
        $owner = Profile::factory()->business()->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/me/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Browse Published Opportunities (GET /api/v1/opportunities)
    |--------------------------------------------------------------------------
    */

    public function test_browse_opportunities_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/opportunities');

        $response->assertStatus(401);
    }

    public function test_browse_opportunities_returns_only_published(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()->forCreator($communityCreator)->create(); // draft
        CollabOpportunity::factory()->count(2)->published()->forCreator($communityCreator)->create();
        CollabOpportunity::factory()->closed()->forCreator($communityCreator)->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_opportunities_shows_opposite_user_type(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityViewer = Profile::factory()->community()->create();
        $businessCreator = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        CollabOpportunity::factory()->count(2)->published()->forCreator($businessCreator)->create();
        CollabOpportunity::factory()->count(3)->published()->forCreator($communityCreator)->create();

        // Business viewer should see community opportunities
        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);

        // Community viewer should see business opportunities
        $response = $this->actingAs($communityViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_opportunities_explicit_creator_type_overrides_default(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $businessCreator = Profile::factory()->business()->create();

        CollabOpportunity::factory()->count(2)->published()->forCreator($businessCreator)->create();

        // Business viewer explicitly requesting business-type opportunities
        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities?creator_type=business');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Browse: Hide Already Applied Opportunities
    |--------------------------------------------------------------------------
    */

    public function test_browse_excludes_opportunities_user_has_applied_to(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity1 = CollabOpportunity::factory()->published()->forCreator($communityCreator)->create();
        $opportunity2 = CollabOpportunity::factory()->published()->forCreator($communityCreator)->create();
        CollabOpportunity::factory()->published()->forCreator($communityCreator)->create(); // not applied

        // Viewer applied to opportunity1 (pending) and opportunity2 (declined)
        Application::factory()->forOpportunity($opportunity1)->forApplicant($businessViewer)->pending()->create();
        Application::factory()->forOpportunity($opportunity2)->forApplicant($businessViewer)->declined()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_excludes_withdrawn_applications(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($communityCreator)->create();
        CollabOpportunity::factory()->published()->forCreator($communityCreator)->create(); // not applied

        Application::factory()->forOpportunity($opportunity)->forApplicant($businessViewer)->withdrawn()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_excludes_accepted_applications(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($communityCreator)->create();
        CollabOpportunity::factory()->count(2)->published()->forCreator($communityCreator)->create();

        Application::factory()->forOpportunity($opportunity)->forApplicant($businessViewer)->accepted()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_shows_opportunities_other_users_applied_to(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $otherBusiness = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($communityCreator)->create();

        // Another user applied, not the viewer
        Application::factory()->forOpportunity($opportunity)->forApplicant($otherBusiness)->pending()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }
}
