<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\PointLedger;
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

    public function test_publish_awards_kolab_published_xp_once(): void
    {
        $community = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $community->id,
            'event_type' => 'kolab_published',
            'reference_id' => $kolab->id,
        ]);

        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $community->id)
            ->where('event_type', 'kolab_published')
            ->where('reference_id', $kolab->id)
            ->count());
    }

    public function test_unsubscribed_business_cannot_publish_venue_promotion(): void
    {
        // Businesses get NO free self-published kolab; the very first venue
        // promotion requires an active subscription.
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->venuePromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');

        $kolab->refresh();
        $this->assertNull($kolab->published_at);
    }

    public function test_unsubscribed_business_cannot_publish_product_promotion(): void
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

    public function test_venue_promotion_publish_is_gated_regardless_of_prior_published_kolabs(): void
    {
        // Regression guard: the paywall must NOT be count-based (no "free quota"),
        // so a prior published kolab neither grants nor consumes a free slot —
        // an unsubscribed business is always gated on a promotional publish.
        $business = Profile::factory()->business()->create();
        Kolab::factory()->venuePromotion()->published()->forCreator($business)->create();

        $kolab = Kolab::factory()->venuePromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_product_promotion_publish_is_gated_regardless_of_prior_published_kolabs(): void
    {
        $business = Profile::factory()->business()->create();
        Kolab::factory()->productPromotion()->published()->forCreator($business)->create();

        $kolab = Kolab::factory()->productPromotion()->forCreator($business)->create(); // draft

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_business_community_seeking_publish_is_always_free(): void
    {
        // CommunitySeeking is always free for any role, including businesses.
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($business)->create(); // community_seeking by default

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
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

    public function test_subscribed_business_can_publish_a_direct_proposal_for_a_single_community(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Casa Sol',
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Casa Sol Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 120,
                'formatted_address' => 'Rambla 10, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);

        $targetCommunity = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $targetCommunity->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        $otherCommunity = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $otherCommunity->id,
            'name' => 'Barcelona Food Club',
            'community_type' => 'food_community',
        ]);

        $kolab = Kolab::factory()->venuePromotion()->forCreator($business)->create([
            'preferred_city' => 'Barcelona',
        ]);

        $response = $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish", [
                'recipient_community_id' => $targetCommunity->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.recipient_community_id', $targetCommunity->id);

        $this->assertDatabaseHas('kolabs', [
            'id' => $kolab->id,
            'status' => 'published',
            'recipient_community_id' => $targetCommunity->id,
        ]);

        $this->actingAs($targetCommunity)
            ->getJson("/api/v1/kolabs/{$kolab->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $kolab->id);

        $this->actingAs($otherCommunity)
            ->getJson("/api/v1/kolabs/{$kolab->id}")
            ->assertStatus(403);

        $this->actingAs($targetCommunity)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id);

        $this->actingAs($otherCommunity)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
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
