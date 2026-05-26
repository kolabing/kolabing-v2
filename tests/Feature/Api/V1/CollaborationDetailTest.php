<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_scheduled_collaboration_detail_exposes_finish_action_capability(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        $collaboration = Collaboration::factory()
            ->scheduled()
            ->forCreator($business)
            ->forApplicant($community)
            ->create();

        $response = $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.actions.can_activate', true)
            ->assertJsonPath('data.actions.can_complete', true)
            ->assertJsonPath('data.actions.can_cancel', true);
    }

    public function test_active_collaboration_detail_exposes_finish_action_capability(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        $collaboration = Collaboration::factory()
            ->active()
            ->forCreator($business)
            ->forApplicant($community)
            ->create();

        $response = $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.actions.can_activate', false)
            ->assertJsonPath('data.actions.can_complete', true)
            ->assertJsonPath('data.actions.can_cancel', true);
    }

    public function test_collaboration_detail_does_not_expose_legacy_feedback_payload(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        $collaboration = Collaboration::factory()
            ->completed()
            ->forCreator($business)
            ->forApplicant($community)
            ->create();

        $response = $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertArrayNotHasKey('feedback', $response->json('data'));
    }

    public function test_legacy_finish_endpoint_is_not_available(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $collaboration = Collaboration::factory()
            ->active()
            ->forCreator($business)
            ->forApplicant($community)
            ->create();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/finish");

        $response->assertNotFound();
    }
}
