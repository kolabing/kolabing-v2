<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationListsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/kolabs/{opp}/applications — creator-only
    |--------------------------------------------------------------------------
    */

    public function test_creator_can_list_applications_for_their_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community1 = Profile::factory()->community()->create();
        $community2 = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community1)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community2)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/kolabs/{$opportunity->id}/applications")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_creator_cannot_list_applications_for_an_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $outsider = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community)->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/kolabs/{$opportunity->id}/applications")
            ->assertStatus(403);
    }

    public function test_applicant_cannot_list_all_applications_for_the_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->getJson("/api/v1/kolabs/{$opportunity->id}/applications")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/me/applications — applicant view
    |--------------------------------------------------------------------------
    */

    public function test_community_sees_their_sent_applications(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity1 = Kolab::factory()->published()->forCreator($business)->create();
        $opportunity2 = Kolab::factory()->published()->forCreator($business)->create();

        Application::factory()->pending()->forKolab($opportunity1)->forApplicant($community)->create();
        Application::factory()->pending()->forKolab($opportunity2)->forApplicant($community)->create();

        $this->actingAs($community)
            ->getJson('/api/v1/me/applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_me_applications_only_shows_own_applications(): void
    {
        $business = Profile::factory()->business()->create();
        $community1 = Profile::factory()->community()->create();
        $community2 = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community1)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community2)->create();

        $this->actingAs($community1)
            ->getJson('/api/v1/me/applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/me/received-applications — creator view
    |--------------------------------------------------------------------------
    */

    public function test_creator_sees_received_applications(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community1 = Profile::factory()->community()->create();
        $community2 = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community1)->create();
        Application::factory()->pending()->forKolab($opportunity)->forApplicant($community2)->create();

        $this->actingAs($business)
            ->getJson('/api/v1/me/received-applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_received_applications_excludes_other_creators_applications(): void
    {
        $business1 = Profile::factory()->business()->create();
        $business2 = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity1 = Kolab::factory()->published()->forCreator($business1)->create();
        $opportunity2 = Kolab::factory()->published()->forCreator($business2)->create();

        Application::factory()->pending()->forKolab($opportunity1)->forApplicant($community)->create();
        Application::factory()->pending()->forKolab($opportunity2)->forApplicant($community)->create();

        $this->actingAs($business1)
            ->getJson('/api/v1/me/received-applications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/applications/{id} — both parties can view, third party cannot
    |--------------------------------------------------------------------------
    */

    public function test_applicant_can_view_their_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forKolab($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $application->id);
    }

    public function test_opportunity_creator_can_view_an_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forKolab($opportunity)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $application->id);
    }

    public function test_third_party_cannot_view_an_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $outsider = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forKolab($opportunity)->forApplicant($community)->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/applications/{$application->id}")
            ->assertStatus(403);
    }
}
