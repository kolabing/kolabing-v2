<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessSubscription;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabApplicationsRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_can_apply_to_a_kolab_via_kolabs_route(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        $kolab = Kolab::factory()->published()->forCreator($business)->create();
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/applications", [
                'message' => 'We would love to collaborate.',
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('applications', [
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'collab_opportunity_id' => null,
        ]);
    }

    public function test_creator_can_list_applications_via_kolabs_route(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        $kolab = Kolab::factory()->published()->forCreator($business)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/kolabs/{$kolab->id}/applications")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
