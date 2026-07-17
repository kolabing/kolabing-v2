<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CollaborationStatus;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardNextActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createCompleteBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Test Cafe',
            'about' => 'A lovely cafe.',
            'business_type' => 'food_drink',
        ]);

        return $profile;
    }

    public function test_next_action_prompts_profile_completion_when_business_profile_missing(): void
    {
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action.key', 'complete_profile');
    }

    public function test_next_action_prompts_first_offer_when_profile_complete_and_no_published_kolabs(): void
    {
        $business = $this->createCompleteBusinessProfile();

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action.key', 'create_first_offer');
    }

    public function test_next_action_prompts_pending_applications_when_present(): void
    {
        $business = $this->createCompleteBusinessProfile();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();
        Application::factory()->forKolab($kolab)->pending()->create();

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action.key', 'review_pending_applications')
            ->assertJsonPath('data.next_action.title', 'Review 1 pending application');
    }

    public function test_next_action_is_null_when_activated_with_no_further_action(): void
    {
        $business = $this->createCompleteBusinessProfile();
        Kolab::factory()->published()->forCreator($business)->create();

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action', null);
    }

    public function test_next_action_prompts_review_for_unreviewed_completed_collaboration(): void
    {
        $business = $this->createCompleteBusinessProfile();
        $community = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->forKolab($kolab)->forApplicant($community)->accepted()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'kolab_id' => $kolab->id,
                'application_id' => $application->id,
                'status' => CollaborationStatus::Completed,
                'completed_at' => now(),
            ]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action.key', 'leave_review');
    }

    public function test_next_action_prompts_second_offer_after_first_kolab_reviewed(): void
    {
        $business = $this->createCompleteBusinessProfile();
        $community = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->forKolab($kolab)->forApplicant($community)->accepted()->create();

        $collaboration = Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'kolab_id' => $kolab->id,
                'application_id' => $application->id,
                'status' => CollaborationStatus::Completed,
                'completed_at' => now(),
            ]);

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'reviewed_profile_id' => $community->id,
            'reviewer_role' => 'creator',
            'rating' => 5,
        ]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.next_action.key', 'create_second_offer');
    }

    public function test_partner_status_block_is_present_with_breakdown(): void
    {
        $business = $this->createCompleteBusinessProfile();

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.partner_status.status', 'new_partner')
            ->assertJsonPath('data.partner_status.label', 'New Partner')
            ->assertJsonStructure([
                'data' => [
                    'partner_status' => [
                        'status', 'label', 'icon',
                        'breakdown' => ['completed_kolabs', 'review_count', 'average_rating', 'repeat_partner_count'],
                    ],
                ],
            ]);
    }
}
