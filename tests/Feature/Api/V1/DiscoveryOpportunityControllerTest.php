<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
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
        // Subscribed viewer: community identity is revealed (the masking of
        // name/logo for a FREE business is covered by its own test below).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
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

    public function test_free_business_viewer_gets_community_identity_masked_in_the_feed(): void
    {
        // A non-subscribed business must NOT receive a community's name/logo.
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
        ]);

        $community = Profile::factory()->community()->create([
            'avatar_url' => 'https://example.com/secret-logo.jpg',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        // No media on the kolab -> cover photo would otherwise fall back to the
        // community avatar; the mask must prevent that leak too.
        Kolab::factory()->published()->forCreator($community)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Need a venue for a wellness morning',
            'preferred_city' => 'Barcelona',
            'media' => [],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.creator_type', 'community')
            ->assertJsonPath('data.data.0.creator_profile.display_name', null)
            ->assertJsonPath('data.data.0.creator_profile.avatar_url', null)
            ->assertJsonPath('data.data.0.creator_profile.identity_locked', true)
            ->assertJsonPath('data.data.0.cover_photo_url', null);

        // The community's real name/logo must appear nowhere in the payload.
        $raw = $response->getContent();
        $this->assertStringNotContainsString('Wellness Collective', $raw);
        $this->assertStringNotContainsString('secret-logo.jpg', $raw);
    }

    public function test_subscribed_business_viewer_sees_community_identity_in_the_feed(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
        ]);

        $community = Profile::factory()->community()->create([
            'avatar_url' => 'https://example.com/community-logo.jpg',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        Kolab::factory()->published()->forCreator($community)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Need a venue for a wellness morning',
            'preferred_city' => 'Barcelona',
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk()
            ->assertJsonPath('data.data.0.creator_profile.display_name', 'Wellness Collective')
            ->assertJsonPath('data.data.0.creator_profile.avatar_url', 'https://example.com/community-logo.jpg')
            ->assertJsonPath('data.data.0.creator_profile.identity_locked', false);
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
            'offer_headline' => 'Post-run cafe takeovers in central Barcelona',
            'base_offer' => 'Reserved cafe space plus drinks for running groups after weekend sessions.',
            'negotiation_triggers' => [
                [
                    'condition' => 'Monthly recurring meetups',
                    'additional_offer' => 'Free pastry platter for the third event onward.',
                ],
            ],
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
            ->assertJsonPath('data.data.0.offer_headline', 'Post-run cafe takeovers in central Barcelona')
            ->assertJsonPath('data.data.0.community_request', null)
            ->assertJsonPath('data.data.0.business_offer.venue_type', 'cafe')
            ->assertJsonPath('data.data.0.business_offer.min_community_size', 80)
            ->assertJsonPath('data.data.0.business_offer.base_offer', 'Reserved cafe space plus drinks for running groups after weekend sessions.')
            ->assertJsonMissingPath('data.data.0.business_offer.negotiation_triggers');

        $offerTypes = $response->json('data.data.0.business_offer.offer_types');
        $seekingCommunities = $response->json('data.data.0.business_offer.seeking_communities');

        $this->assertContains('venue', $offerTypes);
        $this->assertContains('food_drink', $offerTypes);
        $this->assertSame('run_club', $seekingCommunities[0]['key']);
        $this->assertSame('Run Club', $seekingCommunities[0]['label']);
        $this->assertIsInt($response->json('data.data.0.match_score'));
        $this->assertCount(4, $response->json('data.data.0.match_breakdown'));
    }

    public function test_community_viewer_receives_business_activity_metadata_and_neighborhood_area_for_dense_cards(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        $creator = Profile::factory()->business()->create([
            'avatar_url' => 'https://example.com/business-avatar.jpg',
        ]);
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
                'neighborhood' => 'Gracia',
                'photos' => [],
            ],
        ]);

        Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'preferred_city' => 'Barcelona',
            'area' => 'Barcelona',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Run Club'],
            'expects' => ['social_media'],
            'offer_headline' => 'Post-run cafe takeovers in central Barcelona',
            'base_offer' => 'Reserved cafe space plus drinks for running groups after weekend sessions.',
            'published_at' => now()->subDays(2),
            'past_events' => [
                [
                    'name' => 'Rooftop Shakeout',
                    'date' => now()->format('Y-m-d'),
                    'partner_name' => 'Barcelona Run Club',
                ],
                [
                    'name' => 'Sunday Brunch Social',
                    'date' => now()->subMonth()->format('Y-m-d'),
                    'partner_name' => 'City Pacers',
                ],
            ],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.creator_profile.display_name', 'Casa Sol')
            ->assertJsonPath('data.data.0.offer_headline', 'Post-run cafe takeovers in central Barcelona')
            ->assertJsonPath('data.data.0.area', 'Gracia')
            ->assertJsonPath('data.data.0.past_events_count', 2)
            ->assertJsonPath('data.data.0.active_this_month', true)
            ->assertJsonPath('data.data.0.active_this_month_label', 'Active this month');

        $this->assertCount(4, $response->json('data.data.0.match_breakdown'));
    }

    public function test_discovery_omits_area_when_it_only_duplicates_the_city(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Food Club',
            'community_type' => 'food_community',
        ]);

        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Tapas Hub',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Tapas Hub',
                'venue_type' => 'restaurant',
                'capacity' => 90,
                'formatted_address' => 'Carrer Mallorca 12, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'preferred_city' => 'Barcelona',
            'area' => 'Barcelona',
            'offer_headline' => 'Tasting nights for food communities',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Food Community'],
            'expects' => ['social_media'],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.area', null);
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

    public function test_discovery_match_breakdown_weights_sum_to_match_score(): void
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

        Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Run Club'],
            'expects' => ['social_media'],
            'offer_headline' => 'Recovery brunches for run clubs',
            'base_offer' => 'Reserved brunch tables and drink specials for running groups.',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $breakdown = $response->json('data.data.0.match_breakdown');
        $reportedScore = $response->json('data.data.0.match_score');

        $calculatedScore = (int) round(collect($breakdown)->sum(
            fn (array $signal): float => ((float) $signal['weight']) * ((float) $signal['score'])
        ) * 100);

        $this->assertSame($reportedScore, $calculatedScore);
    }

    public function test_food_community_recommended_feed_prefers_cafe_over_coworking(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Barcelona Food Club',
            'community_type' => 'food_community',
        ]);

        $cafeCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $cafeCreator->id,
            'name' => 'Cafe Sol',
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Cafe Sol',
                'venue_type' => 'cafe',
                'capacity' => 90,
                'formatted_address' => 'Rambla 10, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $cafeKolab = Kolab::factory()->published()->venuePromotion()->forCreator($cafeCreator)->create([
            'preferred_city' => 'Barcelona',
            'venue_type' => 'cafe',
            'offering' => ['venue_space', 'free_drinks'],
            'seeking_communities' => ['Foodies', 'Food Community'],
            'expects' => ['social_media'],
            'published_at' => now()->subDays(4),
            'offer_headline' => 'Tasting nights for food communities',
            'base_offer' => 'Reserved cafe space and tasting flights for supper clubs.',
        ]);

        $coworkingCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $coworkingCreator->id,
            'name' => 'Workhaus Barcelona',
            'business_type' => 'coworking',
            'categories' => ['coworking'],
            'city_name' => 'Barcelona',
            'primary_venue' => [
                'name' => 'Workhaus Barcelona',
                'venue_type' => 'coworking',
                'capacity' => 250,
                'formatted_address' => 'Diagonal 22, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $coworkingKolab = Kolab::factory()->published()->venuePromotion()->forCreator($coworkingCreator)->create([
            'preferred_city' => 'Barcelona',
            'venue_type' => 'coworking',
            'offering' => ['venue_space', 'discount'],
            'seeking_communities' => ['Foodies', 'Food Community'],
            'expects' => ['social_media'],
            'published_at' => now()->subHour(),
            'offer_headline' => 'Workspace socials for community dinners',
            'base_offer' => 'Large coworking lounge with discounted room hire for food gatherings.',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2);

        $ids = array_column($response->json('data.data'), 'id');

        $this->assertSame([$cafeKolab->id, $coworkingKolab->id], $ids);
        $this->assertGreaterThan(
            $response->json('data.data.1.match_score'),
            $response->json('data.data.0.match_score')
        );
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

    public function test_discovery_excludes_items_the_current_business_viewer_has_applied_to(): void
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

        $appliedKolab = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Applied request',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        $visibleKolab = Kolab::factory()->published()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Visible request',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
            'availability_start' => now()->addDays(7),
            'availability_end' => now()->addDays(8),
        ]);

        Application::factory()
            ->forKolab($appliedKolab)
            ->forApplicant($viewer)
            ->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $visibleKolab->id);
    }

    public function test_discovery_excludes_items_the_current_community_viewer_has_applied_to(): void
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

        $appliedKolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'title' => 'Applied offer',
            'preferred_city' => 'Barcelona',
            'offering' => ['venue_space'],
            'expects' => ['social_media'],
            'availability_start' => now()->addDays(5),
            'availability_end' => now()->addDays(6),
        ]);

        $visibleKolab = Kolab::factory()->published()->productPromotion()->forCreator($creator)->create([
            'title' => 'Visible offer',
            'preferred_city' => 'Barcelona',
            'offering' => ['products'],
            'expects' => ['review_feedback'],
            'availability_start' => now()->addDays(7),
            'availability_end' => now()->addDays(8),
        ]);

        Application::factory()
            ->forKolab($appliedKolab)
            ->forApplicant($viewer)
            ->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $visibleKolab->id);
    }
}
