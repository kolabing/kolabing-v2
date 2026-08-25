<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\PartnerStatusTier;
use App\Models\BusinessPartnerStatus;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DiscoveryPartnerStatusBoostTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBusinessCreator(string $name, ?PartnerStatusTier $tier = null): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => $name,
                'venue_type' => 'cafe',
                'capacity' => 120,
                'formatted_address' => 'Rambla 10, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        if ($tier !== null) {
            BusinessPartnerStatus::factory()->create([
                'profile_id' => $profile->id,
                'status' => $tier,
            ]);
        }

        return $profile;
    }

    private function createIdenticalVenueKolab(Profile $creator, string $title): Kolab
    {
        return Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'title' => $title,
            'preferred_city' => 'Barcelona',
            'venue_type' => 'cafe',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Run Club'],
            'min_community_size' => 80,
            'expects' => ['social_media'],
            'availability_start' => now()->addDays(10),
            'availability_end' => now()->addDays(12),
        ]);
    }

    private function createCommunityViewer(): Profile
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        return $viewer;
    }

    public function test_trusted_partner_business_kolab_outranks_otherwise_identical_new_partner_kolab(): void
    {
        $viewer = $this->createCommunityViewer();

        $newPartner = $this->createBusinessCreator('New Partner Cafe');
        $trustedPartner = $this->createBusinessCreator('Trusted Partner Cafe', PartnerStatusTier::TrustedPartner);

        $newPartnerKolab = $this->createIdenticalVenueKolab($newPartner, 'New partner offer');
        $trustedPartnerKolab = $this->createIdenticalVenueKolab($trustedPartner, 'Trusted partner offer');

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&sort=recommended');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $trustedPartnerKolab->id)
            ->assertJsonPath('data.data.0.match.partner_status_boost.tier', 'trusted_partner')
            ->assertJsonPath('data.data.0.match.partner_status_boost.points', 5);

        $newPartnerEntry = collect($response->json('data.data'))
            ->firstWhere('id', $newPartnerKolab->id);

        $this->assertSame(0, $newPartnerEntry['match']['partner_status_boost']['points']);
        $this->assertSame('new_partner', $newPartnerEntry['match']['partner_status_boost']['tier']);

        $trustedEntry = collect($response->json('data.data'))
            ->firstWhere('id', $trustedPartnerKolab->id);

        $this->assertGreaterThan($newPartnerEntry['match']['score'], $trustedEntry['match']['score']);
    }

    public function test_community_favourite_boost_is_larger_than_trusted_partner(): void
    {
        $viewer = $this->createCommunityViewer();

        $favourite = $this->createBusinessCreator('Favourite Cafe', PartnerStatusTier::CommunityFavourite);
        $this->createIdenticalVenueKolab($favourite, 'Favourite offer');

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&sort=recommended');

        $response->assertOk()
            ->assertJsonPath('data.data.0.match.partner_status_boost.tier', 'community_favourite')
            ->assertJsonPath('data.data.0.match.partner_status_boost.points', 10);
    }

    public function test_boost_is_not_applied_when_business_views_community_kolabs(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessViewer->id]);

        $communityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $communityCreator->id]);

        $kolab = Kolab::factory()->published()->forCreator($communityCreator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
        ]);

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all');

        $response->assertOk()
            ->assertJsonPath('data.meta.viewer_role', 'business');

        $entry = collect($response->json('data.data'))->firstWhere('id', $kolab->id);

        $this->assertSame(0, $entry['match']['partner_status_boost']['points']);
        $this->assertNull($entry['match']['partner_status_boost']['tier']);
    }

    public function test_score_is_capped_at_one_hundred_after_boost(): void
    {
        $viewer = $this->createCommunityViewer();
        $favourite = $this->createBusinessCreator('Max Score Cafe', PartnerStatusTier::CommunityFavourite);

        $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($favourite)->create([
            'preferred_city' => 'Barcelona',
            'venue_type' => 'cafe',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Run Club'],
            'min_community_size' => 80,
            'expects' => ['social_media'],
            'availability_start' => now()->addDay(),
            'availability_end' => now()->addDays(2),
            'published_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all');

        $entry = collect($response->json('data.data'))->firstWhere('id', $kolab->id);

        $this->assertLessThanOrEqual(100, $entry['match']['score']);
    }
}
