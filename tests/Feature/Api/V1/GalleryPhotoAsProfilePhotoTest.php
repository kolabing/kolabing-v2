<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A user who signs up with gallery photos but no profile photo gets the first
 * gallery photo as their profile photo. An existing profile photo is never
 * overwritten, and the shared file survives replacing or deleting either side.
 */
class GalleryPhotoAsProfilePhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

    public function test_business_signup_without_photo_uses_first_venue_photo(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'gallery-avatar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Gallery Cafe',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Gallery Cafe',
                'venue_type' => 'cafe',
                'capacity' => 40,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => ['https://cdn.example.com/first.jpg', 'https://cdn.example.com/second.jpg'],
            ],
        ]);

        $response->assertCreated();

        $profile = Profile::query()->where('email', 'gallery-avatar@example.com')->firstOrFail();
        $this->assertSame('https://cdn.example.com/first.jpg', $profile->businessProfile->profile_photo);
        $this->assertSame('https://cdn.example.com/first.jpg', $profile->avatar_url);
    }

    public function test_product_business_signup_without_photo_uses_first_offer_photo(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'offer-avatar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Offer Brand',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'has_venue' => false,
            'offer_photos' => ['https://cdn.example.com/offer.jpg'],
        ])->assertCreated();

        $profile = Profile::query()->where('email', 'offer-avatar@example.com')->firstOrFail();
        $this->assertSame('https://cdn.example.com/offer.jpg', $profile->businessProfile->profile_photo);
    }

    public function test_business_signup_without_any_photos_keeps_photo_empty(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'no-photos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bare Cafe',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Bare Cafe',
                'venue_type' => 'cafe',
                'capacity' => 40,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertCreated();

        $profile = Profile::query()->where('email', 'no-photos@example.com')->firstOrFail();
        $this->assertNull($profile->businessProfile->profile_photo);
    }

    public function test_first_gallery_upload_becomes_profile_photo_when_missing(): void
    {
        $profile = Profile::factory()->community()->create(['avatar_url' => null]);
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->post('/api/v1/me/gallery', [
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $first = $response->json('data.0.url');
        $this->assertSame($first, $profile->communityProfile->fresh()->profile_photo);
        $this->assertSame($first, $profile->fresh()->avatar_url);
    }

    public function test_gallery_upload_never_overwrites_existing_profile_photo(): void
    {
        $profile = Profile::factory()->business()->create(['avatar_url' => 'https://cdn.example.com/logo.png']);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'profile_photo' => 'https://cdn.example.com/logo.png',
        ]);

        $this->actingAs($profile)->post('/api/v1/me/gallery', [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('https://cdn.example.com/logo.png', $profile->businessProfile->fresh()->profile_photo);
        $this->assertSame('https://cdn.example.com/logo.png', $profile->fresh()->avatar_url);
    }

    public function test_attendee_gallery_upload_fills_missing_avatar(): void
    {
        $profile = Profile::factory()->attendee()->create(['avatar_url' => null]);

        $response = $this->actingAs($profile)->post('/api/v1/me/gallery', [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertSame($response->json('data.url'), $profile->fresh()->avatar_url);
    }

    public function test_deleting_gallery_photo_used_as_profile_photo_keeps_the_file(): void
    {
        $profile = Profile::factory()->community()->create(['avatar_url' => null]);
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $upload = $this->actingAs($profile)->post('/api/v1/me/gallery', [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $photo = ProfileGalleryPhoto::query()->findOrFail($upload->json('data.id'));
        $path = $this->storagePath($photo->url);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($profile)->deleteJson("/api/v1/me/gallery/{$photo->id}")->assertOk();

        Storage::disk('public')->assertExists($path);
        $this->assertSame($photo->url, $profile->communityProfile->fresh()->profile_photo);
    }

    private function storagePath(string $url): string
    {
        return ltrim((string) preg_replace('#^.*/storage/#', '', (string) parse_url($url, PHP_URL_PATH)), '/');
    }
}
