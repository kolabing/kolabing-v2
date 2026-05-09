<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DiscoveryOpportunityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_discovery_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/discovery/opportunities');

        $response->assertStatus(401);
    }

    public function test_business_viewer_sees_only_active_published_community_requests_with_match_metadata(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Casa Sol Rooftop',
                'venue_type' => 'restaurant',
                'capacity' => 180,
                'formatted_address' => 'Carrer Mallorca 12, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $communityCreator = Profile::factory()->community()->create([
            'avatar_url' => 'https://example.com/community-avatar.jpg',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $communityCreator->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        $matchingKolab = Kolab::factory()->published()->forCreator($communityCreator)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Sunrise beach yoga',
            'description' => 'Looking for a Barcelona venue partner for a sunrise meetup.',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue', 'food_drink'],
            'community_types' => ['Wellness', 'Fitness'],
            'community_size' => 320,
            'typical_attendance' => 90,
            'offers_in_return' => ['social_media', 'community_reach'],
            'venue_preference' => 'business_provides',
            'availability_mode' => 'specific_dates',
            'availability_start' => now()->addDays(7),
            'availability_end' => now()->addDays(8),
            'published_at' => now()->subDay(),
        ]);

        Kolab::factory()->published()->forCreator($communityCreator)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Expired meetup',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Fitness'],
            'community_size' => 100,
            'typical_attendance' => 30,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
            'availability_start' => now()->subDays(10),
            'availability_end' => now()->subDays(5),
        ]);

        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $businessCreator->id,
            'name' => 'Another Business',
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Another Business Venue',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Street 2, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        Kolab::factory()->published()->venuePromotion()->forCreator($businessCreator)->create([
            'preferred_city' => 'Barcelona',
        ]);

        Kolab::factory()->published()->forCreator($viewer)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Fitness'],
            'community_size' => 200,
            'typical_attendance' => 50,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.feed', 'recommended')
            ->assertJsonPath('data.meta.viewer_role', 'business')
            ->assertJsonPath('data.data.0.id', $matchingKolab->id)
            ->assertJsonPath('data.data.0.creator_type', 'community')
            ->assertJsonPath('data.data.0.creator_profile.display_name', 'Wellness Collective')
            ->assertJsonPath('data.data.0.creator_profile.avatar_url', 'https://example.com/community-avatar.jpg')
            ->assertJsonPath('data.data.0.business_offer', null)
            ->assertJsonPath('data.data.0.community_request.need_types', ['venue', 'food_drink'])
            ->assertJsonPath('data.data.0.community_request.venue_preference', 'business_provides')
            ->assertJsonPath('data.data.0.match.feed', 'recommended');

        $this->assertGreaterThan(0, (int) $response->json('data.data.0.match.score'));
        $this->assertContains('city_match', $response->json('data.data.0.match.reasons'));
        $this->assertContains('need_type_match', $response->json('data.data.0.match.reasons'));
    }

    public function test_community_viewer_can_filter_business_offers_and_receives_normalized_offer_data(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        $matchingCreator = Profile::factory()->business()->create([
            'avatar_url' => 'https://example.com/business-avatar.jpg',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $matchingCreator->id,
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

        $matchingKolab = Kolab::factory()->published()->venuePromotion()->forCreator($matchingCreator)->create([
            'preferred_city' => 'Barcelona',
            'venue_type' => 'cafe',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Run Club', 'Fitness'],
            'min_community_size' => 80,
            'expects' => ['social_media', 'community_reach'],
            'availability_start' => now()->addDays(10),
            'availability_end' => now()->addDays(12),
        ]);

        $largeRequirementCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $largeRequirementCreator->id,
            'name' => 'Luxury Lounge',
            'business_type' => 'hotel',
            'categories' => ['hotel'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Luxury Lounge',
                'venue_type' => 'hotel',
                'capacity' => 500,
                'formatted_address' => 'Passeig 11, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        Kolab::factory()->published()->venuePromotion()->forCreator($largeRequirementCreator)->create([
            'preferred_city' => 'Barcelona',
            'venue_type' => 'hotel',
            'offering' => ['venue_space'],
            'seeking_communities' => ['Run Club'],
            'min_community_size' => 2500,
            'expects' => ['social_media'],
            'availability_start' => now()->addDays(6),
            'availability_end' => now()->addDays(9),
        ]);

        $wrongOfferCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $wrongOfferCreator->id,
            'name' => 'Sample Lab',
            'business_type' => 'retail',
            'categories' => ['retail'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Sample Lab Store',
                'venue_type' => 'retail_store',
                'capacity' => 40,
                'formatted_address' => 'Diagonal 22, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        Kolab::factory()->published()->productPromotion()->forCreator($wrongOfferCreator)->create([
            'preferred_city' => 'Barcelona',
            'offering' => ['products'],
            'min_community_size' => 50,
            'expects' => ['review_feedback'],
            'seeking_communities' => ['Foodies'],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&city=Barcelona&offer_types[]=venue&community_requirement_band=small');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.feed', 'all')
            ->assertJsonPath('data.meta.viewer_role', 'community')
            ->assertJsonPath('data.data.0.id', $matchingKolab->id)
            ->assertJsonPath('data.data.0.creator_type', 'business')
            ->assertJsonPath('data.data.0.creator_profile.display_name', 'Casa Sol')
            ->assertJsonPath('data.data.0.creator_profile.avatar_url', 'https://example.com/business-avatar.jpg')
            ->assertJsonPath('data.data.0.community_request', null)
            ->assertJsonPath('data.data.0.business_offer.venue_type', 'cafe')
            ->assertJsonPath('data.data.0.business_offer.min_community_size', 80);

        $offerTypes = $response->json('data.data.0.business_offer.offer_types');
        $seekingCommunities = $response->json('data.data.0.business_offer.seeking_communities');

        $this->assertContains('venue', $offerTypes);
        $this->assertContains('food_drink', $offerTypes);
        $this->assertSame('run_club', $seekingCommunities[0]['key']);
        $this->assertSame('Run Club', $seekingCommunities[0]['label']);
    }

    public function test_recommended_feed_orders_stronger_matches_first(): void
    {
        $barcelona = City::factory()->create([
            'name' => 'Barcelona',
        ]);

        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Sunrise Run Club',
            'community_type' => 'run_club',
            'city_id' => $barcelona->id,
        ]);

        $matchingCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $matchingCreator->id,
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

        $highMatch = Kolab::factory()->published()->venuePromotion()->forCreator($matchingCreator)->create([
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space', 'social_media'],
            'seeking_communities' => ['Run Club'],
            'expects' => ['social_media'],
            'published_at' => now()->subDays(3),
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        $lowMatchCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $lowMatchCreator->id,
            'name' => 'Madrid Market',
            'business_type' => 'retail',
            'categories' => ['retail'],
            'city_name' => 'Madrid',
            'primary_venue' => [
                'name' => 'Madrid Market',
                'venue_type' => 'retail_store',
                'capacity' => 60,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => 'Madrid',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $lowMatch = Kolab::factory()->published()->productPromotion()->forCreator($lowMatchCreator)->create([
            'preferred_city' => 'Madrid',
            'offering' => ['products'],
            'seeking_communities' => ['Book Club'],
            'expects' => ['review_feedback'],
            'published_at' => now()->subHour(),
            'availability_start' => now()->addDays(4),
            'availability_end' => now()->addDays(9),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2);

        $items = $response->json('data.data');

        $this->assertSame($highMatch->id, $items[0]['id']);
        $this->assertSame($lowMatch->id, $items[1]['id']);
        $this->assertContains('city_match', $items[0]['match']['reasons']);
        $this->assertContains('community_affinity_match', $items[0]['match']['reasons']);
        $this->assertGreaterThan($items[1]['match']['score'], $items[0]['match']['score']);
    }

    public function test_all_feed_can_sort_by_ending_soon(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Casa Sol Rooftop',
                'venue_type' => 'restaurant',
                'capacity' => 180,
                'formatted_address' => 'Carrer Mallorca 12, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $creator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Community Group',
            'community_type' => 'wellness_community',
        ]);

        $later = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 180,
            'typical_attendance' => 70,
            'offers_in_return' => ['social_media'],
            'availability_start' => now()->addDays(3),
            'availability_end' => now()->addDays(10),
        ]);

        $sooner = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 150,
            'typical_attendance' => 60,
            'offers_in_return' => ['social_media'],
            'availability_start' => now()->addDays(2),
            'availability_end' => now()->addDays(4),
        ]);

        $noEndDate = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 220,
            'typical_attendance' => 100,
            'offers_in_return' => ['social_media'],
            'availability_start' => null,
            'availability_end' => null,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&sort=ending_soon');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 3);

        $ids = array_column($response->json('data.data'), 'id');

        $this->assertSame([$sooner->id, $later->id, $noEndDate->id], $ids);
    }

    public function test_business_viewer_can_filter_by_audience_size_band_when_typical_attendance_matches_band(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Casa Sol Rooftop',
                'venue_type' => 'restaurant',
                'capacity' => 180,
                'formatted_address' => 'Carrer Mallorca 12, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $creator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        $matchingByAttendance = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 1200,
            'typical_attendance' => 60,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
        ]);

        Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 900,
            'typical_attendance' => 180,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&audience_size_band=small');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $matchingByAttendance->id);
    }

    public function test_common_search_filters_discovery_results(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
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

        $matchingKolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'title' => 'Sunset rooftop social',
            'description' => 'Rooftop event for local creator groups.',
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space'],
            'expects' => ['social_media'],
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        Kolab::factory()->published()->productPromotion()->forCreator($creator)->create([
            'title' => 'Protein tasting session',
            'description' => 'Sample packs for fitness groups.',
            'preferred_city' => 'Barcelona',
            'offering' => ['products'],
            'expects' => ['review_feedback'],
            'availability_start' => now()->addDays(7),
            'availability_end' => now()->addDays(9),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&search=rooftop');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $matchingKolab->id);
    }

    public function test_recommended_match_score_includes_freshness_signal(): void
    {
        $barcelona = City::factory()->create([
            'name' => 'Barcelona',
        ]);

        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Sunrise Run Club',
            'community_type' => 'run_club',
            'city_id' => $barcelona->id,
        ]);

        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
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

        $olderKolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space', 'social_media'],
            'seeking_communities' => ['Run Club'],
            'expects' => ['social_media'],
            'published_at' => now()->subDays(40),
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        $freshKolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space', 'social_media'],
            'seeking_communities' => ['Run Club'],
            'expects' => ['social_media'],
            'published_at' => now()->subDays(2),
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 2);

        $items = $response->json('data.data');

        $this->assertSame($freshKolab->id, $items[0]['id']);
        $this->assertSame($olderKolab->id, $items[1]['id']);
        $this->assertContains('freshness_match', $items[0]['match']['reasons']);
        $this->assertGreaterThan($items[1]['match']['score'], $items[0]['match']['score']);
    }
}
