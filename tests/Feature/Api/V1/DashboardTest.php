<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/dashboard');

        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Business Dashboard
    |--------------------------------------------------------------------------
    */

    public function test_business_dashboard_returns_opportunity_stats(): void
    {
        $business = Profile::factory()->business()->create();

        // Create opportunities in various statuses
        CollabOpportunity::factory()->forCreator($business)->create(); // draft
        CollabOpportunity::factory()->published()->forCreator($business)->count(3)->create();
        CollabOpportunity::factory()->closed()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.opportunities.total', 5)
            ->assertJsonPath('data.opportunities.published', 3)
            ->assertJsonPath('data.opportunities.draft', 1)
            ->assertJsonPath('data.opportunities.closed', 1);
    }

    public function test_business_dashboard_returns_received_application_stats(): void
    {
        $business = Profile::factory()->business()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();

        // Create applications in various statuses
        Application::factory()->count(3)->forOpportunity($opportunity)->pending()->create();
        Application::factory()->count(2)->forOpportunity($opportunity)->accepted()->create();
        Application::factory()->forOpportunity($opportunity)->declined()->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.applications_received.total', 6)
            ->assertJsonPath('data.applications_received.pending', 3)
            ->assertJsonPath('data.applications_received.accepted', 2)
            ->assertJsonPath('data.applications_received.declined', 1);
    }

    public function test_business_dashboard_returns_collaboration_stats(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();

        $application1 = Application::factory()->forOpportunity($opportunity)->forApplicant($community)->accepted()->create();
        $application2 = Application::factory()->forOpportunity($opportunity)->accepted()->create();
        $application3 = Application::factory()->forOpportunity($opportunity)->accepted()->create();
        $application4 = Application::factory()->forOpportunity($opportunity)->accepted()->create();

        // Scheduled upcoming
        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity)
            ->forApplication($application1)
            ->scheduled()
            ->scheduledOn(now()->addDays(5)->toDateString())
            ->create();

        // Active
        Collaboration::factory()
            ->forCreator($business)
            ->forApplication($application2)
            ->forOpportunity($opportunity)
            ->active()
            ->create();

        // Completed
        Collaboration::factory()
            ->forCreator($business)
            ->forApplication($application3)
            ->forOpportunity($opportunity)
            ->completed()
            ->create();

        // Cancelled
        Collaboration::factory()
            ->forCreator($business)
            ->forApplication($application4)
            ->forOpportunity($opportunity)
            ->cancelled()
            ->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.collaborations.total', 4)
            ->assertJsonPath('data.collaborations.active', 1)
            ->assertJsonPath('data.collaborations.upcoming', 1)
            ->assertJsonPath('data.collaborations.completed', 1);
    }

    public function test_business_dashboard_returns_upcoming_collaborations(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->forOpportunity($opportunity)->forApplicant($community)->accepted()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity)
            ->forApplication($application)
            ->scheduled()
            ->scheduledOn(now()->addDays(3)->toDateString())
            ->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.upcoming_collaborations')
            ->assertJsonStructure([
                'data' => [
                    'upcoming_collaborations' => [
                        '*' => [
                            'id',
                            'status',
                            'scheduled_date',
                            'opportunity' => ['id', 'title', 'categories'],
                            'partner' => ['id', 'name', 'user_type'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_business_dashboard_does_not_include_past_collaborations_in_upcoming(): void
    {
        $business = Profile::factory()->business()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->forOpportunity($opportunity)->accepted()->create();

        // Past scheduled collaboration
        Collaboration::factory()
            ->forCreator($business)
            ->forOpportunity($opportunity)
            ->forApplication($application)
            ->scheduled()
            ->scheduledOn(now()->subDays(5)->toDateString())
            ->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.upcoming_collaborations');
    }

    public function test_business_dashboard_does_not_include_other_users_data(): void
    {
        $business = Profile::factory()->business()->create();
        $otherBusiness = Profile::factory()->business()->create();

        // Other user's opportunity
        CollabOpportunity::factory()->published()->forCreator($otherBusiness)->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.opportunities.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Community Dashboard
    |--------------------------------------------------------------------------
    */

    public function test_community_dashboard_returns_sent_application_stats(): void
    {
        $community = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        // Each application needs a different opportunity due to unique constraint
        $opportunities = CollabOpportunity::factory()->published()->forCreator($business)->count(5)->create();

        // Create applications in various statuses
        Application::factory()->forOpportunity($opportunities[0])->forApplicant($community)->pending()->create();
        Application::factory()->forOpportunity($opportunities[1])->forApplicant($community)->pending()->create();
        Application::factory()->forOpportunity($opportunities[2])->forApplicant($community)->accepted()->create();
        Application::factory()->forOpportunity($opportunities[3])->forApplicant($community)->declined()->create();
        Application::factory()->forOpportunity($opportunities[4])->forApplicant($community)->withdrawn()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applications_sent.total', 5)
            ->assertJsonPath('data.applications_sent.pending', 2)
            ->assertJsonPath('data.applications_sent.accepted', 1)
            ->assertJsonPath('data.applications_sent.declined', 1)
            ->assertJsonPath('data.applications_sent.withdrawn', 1);
    }

    public function test_community_dashboard_returns_collaboration_stats(): void
    {
        $community = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();
        $opportunity1 = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $opportunity2 = CollabOpportunity::factory()->published()->forCreator($business)->create();

        $application1 = Application::factory()->forOpportunity($opportunity1)->forApplicant($community)->accepted()->create();
        $application2 = Application::factory()->forOpportunity($opportunity2)->forApplicant($community)->accepted()->create();

        // Scheduled upcoming
        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity1)
            ->forApplication($application1)
            ->scheduled()
            ->scheduledOn(now()->addDays(7)->toDateString())
            ->create();

        // Completed
        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity2)
            ->forApplication($application2)
            ->completed()
            ->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.collaborations.total', 2)
            ->assertJsonPath('data.collaborations.upcoming', 1)
            ->assertJsonPath('data.collaborations.completed', 1);
    }

    public function test_community_dashboard_returns_upcoming_collaborations(): void
    {
        $community = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->forOpportunity($opportunity)->forApplicant($community)->accepted()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity)
            ->forApplication($application)
            ->scheduled()
            ->scheduledOn(now()->addDays(2)->toDateString())
            ->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.upcoming_collaborations');
    }

    public function test_community_dashboard_does_not_have_opportunities_key(): void
    {
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonMissing(['opportunities']);
    }

    public function test_community_dashboard_does_not_include_other_users_applications(): void
    {
        $community = Profile::factory()->community()->create();
        $otherCommunity = Profile::factory()->community()->create();

        // Other user's application
        Application::factory()->forApplicant($otherCommunity)->pending()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.applications_sent.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    public function test_business_dashboard_returns_zeros_when_empty(): void
    {
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.opportunities.total', 0)
            ->assertJsonPath('data.applications_received.total', 0)
            ->assertJsonPath('data.collaborations.total', 0)
            ->assertJsonPath('data.upcoming_collaborations', []);
    }

    public function test_community_dashboard_returns_zeros_when_empty(): void
    {
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.applications_sent.total', 0)
            ->assertJsonPath('data.collaborations.total', 0)
            ->assertJsonPath('data.upcoming_collaborations', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Response Structure
    |--------------------------------------------------------------------------
    */

    public function test_business_dashboard_response_structure(): void
    {
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'opportunities' => ['total', 'published', 'draft', 'closed'],
                    'applications_received' => ['total', 'pending', 'accepted', 'declined'],
                    'collaborations' => ['total', 'active', 'upcoming', 'completed'],
                    'upcoming_collaborations',
                ],
            ]);
    }

    public function test_community_dashboard_response_structure(): void
    {
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/me/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'applications_sent' => ['total', 'pending', 'accepted', 'declined', 'withdrawn'],
                    'collaborations' => ['total', 'active', 'upcoming', 'completed'],
                    'upcoming_collaborations',
                ],
            ]);
    }
}
