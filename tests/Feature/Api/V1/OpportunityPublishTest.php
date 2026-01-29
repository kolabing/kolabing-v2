<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityPublishTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_user_can_publish_own_draft_opportunity(): void
    {
        $community = Profile::factory()->community()->create();
        $opportunity = CollabOpportunity::factory()->forCreator($community)->create(); // draft

        $response = $this->actingAs($community)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_business_user_without_subscription_cannot_publish(): void
    {
        $business = Profile::factory()->business()->create();
        $opportunity = CollabOpportunity::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/publish");

        $response->assertStatus(403);
    }

    public function test_user_cannot_publish_another_users_opportunity(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $opportunity = CollabOpportunity::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/publish");

        $response->assertStatus(403);
    }

    public function test_cannot_publish_already_published_opportunity(): void
    {
        $community = Profile::factory()->community()->create();
        $opportunity = CollabOpportunity::factory()->published()->forCreator($community)->create();

        $response = $this->actingAs($community)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/publish");

        $response->assertStatus(403);
    }
}
