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
}
