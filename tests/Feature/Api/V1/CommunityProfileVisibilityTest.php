<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A free (non-subscribed) business must not be able to see any community
 * profile. The community identity/profile is only revealed once the business
 * subscribes. Communities, and businesses viewing businesses, are unaffected.
 */
class CommunityProfileVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeCommunity(): Profile
    {
        $community = Profile::factory()->community()->create([
            'avatar_url' => 'https://example.com/community-logo.jpg',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        return $community;
    }

    private function makeBusiness(bool $subscribed): Profile
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
        ]);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        }

        return $business;
    }

    public function test_free_business_cannot_open_community_public_profile(): void
    {
        $business = $this->makeBusiness(subscribed: false);
        $community = $this->makeCommunity();

        $response = $this->actingAs($business)
            ->getJson("/api/v1/communities/{$community->id}/public-profile");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true)
            ->assertJsonPath('code', 'subscription_required');

        $this->assertStringNotContainsString('Wellness Collective', $response->getContent());
    }

    public function test_free_business_cannot_open_community_via_profiles_endpoint(): void
    {
        $business = $this->makeBusiness(subscribed: false);
        $community = $this->makeCommunity();

        $response = $this->actingAs($business)
            ->getJson("/api/v1/profiles/{$community->id}");

        $response->assertStatus(403)
            ->assertJsonPath('code', 'subscription_required');

        $this->assertStringNotContainsString('Wellness Collective', $response->getContent());
    }

    public function test_subscribed_business_can_open_community_public_profile(): void
    {
        $business = $this->makeBusiness(subscribed: true);
        $community = $this->makeCommunity();

        $this->actingAs($business)
            ->getJson("/api/v1/communities/{$community->id}/public-profile")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_subscribed_business_can_open_community_via_profiles_endpoint(): void
    {
        $business = $this->makeBusiness(subscribed: true);
        $community = $this->makeCommunity();

        $this->actingAs($business)
            ->getJson("/api/v1/profiles/{$community->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_community_can_open_another_community_profile(): void
    {
        $viewer = Profile::factory()->community()->create();
        $community = $this->makeCommunity();

        $this->actingAs($viewer)
            ->getJson("/api/v1/communities/{$community->id}/public-profile")
            ->assertOk();
    }

    public function test_free_business_can_still_open_a_business_profile(): void
    {
        $viewer = $this->makeBusiness(subscribed: false);

        $target = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $target->id,
            'name' => 'Other Cafe',
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_name' => 'Madrid',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
