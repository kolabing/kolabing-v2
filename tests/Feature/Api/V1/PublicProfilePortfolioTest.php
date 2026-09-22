<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicProfilePortfolioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function galleryPhotoFor(Profile $profile, int $order): ProfileGalleryPhoto
    {
        return ProfileGalleryPhoto::query()->create([
            'profile_id' => $profile->id,
            'url' => "https://example.com/{$profile->id}-{$order}.jpg",
            'sort_order' => $order,
        ]);
    }

    private function pastEventFor(Profile $profile, string $name): Event
    {
        $event = Event::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'event_date' => '2026-05-01',
        ]);

        EventPhoto::query()->create([
            'event_id' => $event->id,
            'url' => "https://example.com/{$event->id}.jpg",
            'sort_order' => 0,
        ]);

        return $event;
    }

    public function test_a_community_profile_emits_the_portfolio(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $profile = Profile::factory()->community()->create();
        $this->galleryPhotoFor($profile, 0);
        $this->pastEventFor($profile, 'Rooftop Session');

        $data = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data['gallery']);
        $this->assertCount(1, $data['past_events']);
        $this->assertSame(1, $data['past_events_count']);
        $this->assertSame('Rooftop Session', $data['past_events'][0]['name']);
    }

    public function test_a_business_profile_emits_the_portfolio(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $profile = Profile::factory()->business()->create();
        $this->galleryPhotoFor($profile, 0);
        $this->pastEventFor($profile, 'Tasting Night');

        $data = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data['gallery']);
        $this->assertSame(1, $data['past_events_count']);
    }

    /**
     * `business_profiles.offer_photos` (the Google Maps import) never fed the
     * in-app gallery a community member actually sees -- caught live 2026-09-15
     * benchmarking against real listing platforms.
     */
    public function test_business_offer_photos_appear_in_the_in_app_gallery_alongside_uploaded_gallery_photos(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $profile = Profile::factory()->business()->create();
        \App\Models\BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'offer_photos' => ['https://example.com/offer-a.jpg', 'https://example.com/offer-b.jpg'],
        ]);
        $this->galleryPhotoFor($profile, 0);

        $data = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $data['gallery']);
        $this->assertContains('https://example.com/offer-a.jpg', array_column($data['gallery'], 'url'));
        $this->assertContains('https://example.com/offer-b.jpg', array_column($data['gallery'], 'url'));
    }

    public function test_an_attendee_profile_still_returns_200_without_a_portfolio(): void
    {
        // getPublicProfileDetail() throws ModelNotFoundException for attendees, so
        // calling it unguarded would turn every attendee profile into a 404.
        $viewer = Profile::factory()->attendee()->create();
        $attendee = Profile::factory()->attendee()->create(['name' => 'Ada', 'handle' => 'ada']);

        $data = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$attendee->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('gallery', $data);
        $this->assertArrayNotHasKey('past_events', $data);
        $this->assertSame('ada', $data['handle']);
    }

    public function test_the_gallery_is_returned_in_sort_order(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->galleryPhotoFor($profile, 0);
        $b = $this->galleryPhotoFor($profile, 1);

        $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $gallery = $this->actingAs($profile)
            ->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->json('data.gallery');

        $this->assertCount(2, $gallery);
        // The reorder above put $b first; the public block must honour it.
        $this->assertSame([$b->url, $a->url], array_column($gallery, 'url'));
    }
}
