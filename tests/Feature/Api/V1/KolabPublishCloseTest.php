<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessSubscription;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabPublishCloseTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ── Publish ─────────────────────────────────────────────────────────

    public function test_community_user_can_publish_community_seeking_without_subscription(): void
    {
        $community = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($community)->create(); // draft, community_seeking by default

        $response = $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('kolabs', [
            'id' => $kolab->id,
            'status' => 'published',
        ]);

        $kolab->refresh();
        $this->assertNotNull($kolab->published_at);
    }

    public function test_venue_promotion_requires_subscription_to_publish(): void
    {
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->venuePromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_product_promotion_requires_subscription_to_publish(): void
    {
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->productPromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_business_with_subscription_can_publish_venue_promotion(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);
        $kolab = Kolab::factory()->venuePromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');

        $kolab->refresh();
        $this->assertNotNull($kolab->published_at);
    }

    public function test_cannot_publish_already_published_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_publish_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create(); // draft

        $response = $this->actingAs($other)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(403);
    }

    // ── Close ───────────────────────────────────────────────────────────

    public function test_creator_can_close_published_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('kolabs', [
            'id' => $kolab->id,
            'status' => 'closed',
        ]);
    }

    public function test_cannot_close_draft_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create(); // draft

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_close_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(403);
    }
}
