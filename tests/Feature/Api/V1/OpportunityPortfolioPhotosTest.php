<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CollabOpportunity;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityPortfolioPhotosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opportunity_listing_includes_portfolio_photos_from_events(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(3)->for($event)->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(3, $creatorProfile['portfolio_photos']);
        $this->assertArrayHasKey('url', $creatorProfile['portfolio_photos'][0]);
    }

    public function test_opportunity_listing_includes_portfolio_photos_from_gallery(): void
    {
        $business = Profile::factory()->business()->create();

        ProfileGalleryPhoto::factory()->count(2)->for($business, 'profile')->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(2, $creatorProfile['portfolio_photos']);
    }

    public function test_portfolio_photos_merges_events_and_gallery_max_10(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(8)->for($event)->create();
        ProfileGalleryPhoto::factory()->count(5)->for($business, 'profile')->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertLessThanOrEqual(10, count($creatorProfile['portfolio_photos']));
    }

    public function test_portfolio_photos_empty_when_no_media(): void
    {
        $business = Profile::factory()->business()->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertEmpty($creatorProfile['portfolio_photos']);
    }

    public function test_opportunity_show_includes_portfolio_photos(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(2)->for($event)->create();

        $opportunity = CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(2, $creatorProfile['portfolio_photos']);
    }
}
