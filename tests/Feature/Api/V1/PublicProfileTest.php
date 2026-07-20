<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public Profile (GET /api/v1/profiles/{profile})
    |--------------------------------------------------------------------------
    */

    public function test_public_profile_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_public_profile_returns_business_profile(): void
    {
        $viewer = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $business->id)
            ->assertJsonPath('data.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_type',
                    'display_name',
                    'avatar_url',
                    'about',
                    'type',
                    'city_name',
                    'instagram',
                    'tiktok',
                    'website',
                    'profile_photo',
                    'recent_reviews',
                ],
            ]);
    }

    public function test_public_profile_returns_community_profile(): void
    {
        $viewer = Profile::factory()->business()->create();
        // Subscribed business: may view community profiles (a free business is 403'd).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$community->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user_type', 'community');
    }

    public function test_public_profile_attendee_falls_back_to_base_name(): void
    {
        $viewer = Profile::factory()->community()->create();
        $attendee = Profile::factory()->attendee()->create([
            'name' => 'Maria Smiles',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$attendee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user_type', 'attendee')
            ->assertJsonPath('data.display_name', 'Maria Smiles');

        $this->assertNotNull($response->json('data.display_name'));
    }

    public function test_public_profile_does_not_expose_sensitive_data(): void
    {
        $viewer = Profile::factory()->business()->create();
        // Subscribed business: may view community profiles (a free business is 403'd).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
        $target = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone_number', $data);
        $this->assertArrayNotHasKey('google_id', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('review_stats', $data);
    }

    public function test_public_profile_returns_recent_reviews_preview(): void
    {
        $viewer = Profile::factory()->business()->create();
        // Subscribed business: may view community profiles (a free business is 403'd).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
        $target = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $target->id,
            'name' => 'Target Community',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $reviewer = Profile::factory()->business()->create([
                'avatar_url' => "https://example.com/reviewer-{$i}.jpg",
            ]);
            BusinessProfile::factory()->create([
                'profile_id' => $reviewer->id,
                'name' => "Reviewer {$i}",
            ]);

            $collaboration = Collaboration::factory()
                ->completed()
                ->forCreator($reviewer)
                ->forApplicant($target)
                ->create();

            $body = "Review body {$i}";
            CollaborationReview::query()->forceCreate([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $reviewer->id,
                'reviewed_profile_id' => $target->id,
                'reviewer_role' => 'creator',
                'rating' => 5,
                'note' => $body,
                'body' => $body,
                'would_collaborate_again' => true,
                'created_at' => now()->addMinutes($i),
                'updated_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}");

        $response->assertOk()
            ->assertJsonPath('data.recent_reviews.0.body', 'Review body 4')
            ->assertJsonPath('data.recent_reviews.1.body', 'Review body 3')
            ->assertJsonPath('data.recent_reviews.2.body', 'Review body 2')
            ->assertJsonCount(3, 'data.recent_reviews')
            ->assertJsonPath('data.recent_reviews.0.reviewer.display_name', 'Reviewer 4');
    }

    public function test_public_profile_returns_404_for_nonexistent(): void
    {
        $viewer = Profile::factory()->business()->create();
        // Subscribed business: may view community profiles (a free business is 403'd).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_business_tiktok_is_null(): void
    {
        $viewer = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.tiktok', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Community Public Profile (GET /api/v1/communities/{community}/public-profile)
    |--------------------------------------------------------------------------
    */

    public function test_community_public_profile_requires_authentication(): void
    {
        $community = Profile::factory()->community()->create();

        $response = $this->getJson("/api/v1/communities/{$community->id}/public-profile");

        $response->assertStatus(401);
    }

    public function test_community_public_profile_returns_safe_stats_and_past_events_portfolio(): void
    {
        $viewer = Profile::factory()->business()->create();
        // Subscribed business: may view community profiles (a free business is 403'd).
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);
        $community = Profile::factory()->community()->create();
        $partner = Profile::factory()->business()->create([
            'avatar_url' => 'https://example.com/partner-avatar.jpg',
        ]);

        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Barcelona Food Club',
            'about' => 'We host supper clubs and tasting nights around the city.',
            'community_type' => 'food_community',
            'instagram' => 'barcelonafoodclub',
            'tiktok' => 'barcelonafoodclubtok',
            'website' => 'https://barcelonafoodclub.test',
            'profile_photo' => 'https://example.com/community-profile.jpg',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $partner->id,
            'name' => 'Casa Sol',
        ]);

        ProfileGalleryPhoto::factory()->forProfile($community)->create([
            'url' => 'https://example.com/gallery-1.jpg',
            'sort_order' => 0,
        ]);

        Collaboration::factory()->completed()->forCreator($community)->forApplicant($partner)->create([
            'completed_at' => now()->subDay(),
        ]);
        Collaboration::factory()->completed()->forApplicant($community)->forCreator($partner)->create([
            'completed_at' => now()->subDays(2),
        ]);
        Collaboration::factory()->active()->forCreator($community)->create();

        Kolab::factory()->published()->forCreator($community)->create([
            'intent_type' => 'community_seeking',
            'past_events' => [
                [
                    'name' => 'Sunset Supper Club',
                    'date' => '2026-05-01',
                    'partner_name' => 'Casa Sol',
                    'photos' => [
                        'https://example.com/supper-club-1.jpg',
                    ],
                ],
                [
                    'name' => 'Tapas Tasting',
                    'date' => '2026-04-12',
                    'partner_name' => 'Tapas Hub',
                    'media' => [
                        [
                            'url' => 'https://example.com/tapas-tasting.mp4',
                            'type' => 'video',
                            'thumbnail_url' => 'https://example.com/tapas-tasting-thumb.jpg',
                            'sort_order' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/communities/{$community->id}/public-profile");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $community->id)
            ->assertJsonPath('data.user_type', 'community')
            ->assertJsonPath('data.display_name', 'Barcelona Food Club')
            ->assertJsonPath('data.type', 'food_community')
            ->assertJsonPath('data.community_type', 'food_community')
            ->assertJsonPath('data.instagram', 'barcelonafoodclub')
            ->assertJsonPath('data.tiktok', 'barcelonafoodclubtok')
            ->assertJsonPath('data.website', 'https://barcelonafoodclub.test')
            ->assertJsonPath('data.public_stats.completed_collaborations_count', 2)
            ->assertJsonPath('data.public_stats.past_events_count', 2)
            ->assertJsonPath('data.gallery.0.url', 'https://example.com/gallery-1.jpg')
            ->assertJsonPath('data.photos.0.url', 'https://example.com/community-profile.jpg')
            ->assertJsonPath('data.past_events.0.name', 'Sunset Supper Club')
            ->assertJsonPath('data.past_events.0.media.0.type', 'image')
            ->assertJsonPath('data.past_events.1.media.0.type', 'video')
            ->assertJsonPath('data.past_collaborations.0.partner_name', 'Casa Sol')
            ->assertJsonPath('data.past_collaborations.0.partner_avatar_url', 'https://example.com/partner-avatar.jpg');

        $data = $response->json('data');

        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('google_id', $data);
        $this->assertArrayNotHasKey('apple_id', $data);
        $this->assertArrayNotHasKey('is_featured', $data);
        $this->assertArrayHasKey('avatar_url', $data);
    }

    public function test_community_public_profile_returns_404_for_non_community_profiles(): void
    {
        $viewer = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/communities/{$business->id}/public-profile");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Collaborations (GET /api/v1/profiles/{profile}/collaborations)
    |--------------------------------------------------------------------------
    */

    public function test_profile_collaborations_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}/collaborations");

        $response->assertStatus(401);
    }

    public function test_profile_reviews_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}/reviews");

        $response->assertStatus(401);
    }

    public function test_profile_reviews_returns_paginated_received_reviews(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $target->id,
            'name' => 'Target Business',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $reviewer = Profile::factory()->community()->create();
            CommunityProfile::factory()->create([
                'profile_id' => $reviewer->id,
                'name' => "Community Reviewer {$i}",
            ]);

            $collaboration = Collaboration::factory()
                ->completed()
                ->forCreator($target)
                ->forApplicant($reviewer)
                ->create();

            CollaborationReview::query()->forceCreate([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $reviewer->id,
                'reviewed_profile_id' => $target->id,
                'reviewer_role' => 'applicant',
                'rating' => 4,
                'note' => "Review {$i}",
                'body' => "Review {$i}",
                'would_collaborate_again' => $i % 2 === 0,
                'created_at' => now()->addMinutes($i),
                'updated_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/reviews?per_page=10");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.body', 'Review 12')
            ->assertJsonPath('data.0.reviewer.display_name', 'Community Reviewer 12');
    }

    public function test_profile_collaborations_returns_only_completed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        // Completed collaborations (as creator)
        Collaboration::factory()->count(2)->completed()->forCreator($target)->create();

        // Non-completed (should not appear)
        Collaboration::factory()->scheduled()->forCreator($target)->create();
        Collaboration::factory()->active()->forCreator($target)->create();
        Collaboration::factory()->cancelled()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_profile_collaborations_includes_applicant_role(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->community()->create();

        // Collaboration where target is the applicant
        Collaboration::factory()->completed()->forApplicant($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_profile_collaborations_returns_correct_structure(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'partner_name',
                            'partner_avatar_url',
                            'completed_at',
                            'status',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_profile_collaborations_returns_empty_when_none(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_profile_collaborations_supports_pagination(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->count(5)->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations?per_page=2");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_profile_collaborations_status_is_always_completed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200);

        $items = $response->json('data.data');
        foreach ($items as $item) {
            $this->assertEquals('completed', $item['status']);
        }
    }
}
