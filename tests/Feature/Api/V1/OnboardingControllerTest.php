<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = City::factory()->create(['name' => 'Barcelona']);
    }

    public function test_business_onboarding_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/onboarding/business', [
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $this->city->id,
            'instagram' => 'testbusiness',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_business_onboarding_requires_business_user_type(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_business_onboarding_validates_required_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'business_type', 'city_id'],
            ]);
    }

    public function test_business_onboarding_validates_business_type(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'invalid_type',
                'city_id' => $this->city->id,
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['business_type'],
            ]);
    }

    public function test_business_onboarding_validates_city_exists(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => '00000000-0000-0000-0000-000000000000',
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['city_id'],
            ]);
    }

    public function test_business_onboarding_validates_phone_number_format(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'phone_number' => '123456789', // Invalid format
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['phone_number'],
            ]);
    }

    public function test_business_onboarding_completes_successfully(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'about' => 'A cozy cafe in the heart of Barcelona',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'phone_number' => '+34612345678',
                'instagram' => '@cafebarcelona',
                'website' => 'https://cafebarcelona.com',
                'primary_venue' => [
                    'name' => 'Cafe Barcelona Terrace',
                    'venue_type' => 'cafe',
                    'capacity' => 80,
                    'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'photos' => [],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Business profile updated successfully')
            ->assertJsonPath('data.phone_number', '+34612345678')
            ->assertJsonPath('data.business_profile.name', 'Cafe Barcelona')
            ->assertJsonPath('data.business_profile.business_type', 'cafe')
            ->assertJsonPath('data.business_profile.instagram', 'cafebarcelona'); // @ stripped

        // Verify database updates
        $profile->refresh();
        $this->assertEquals('+34612345678', $profile->phone_number);

        $businessProfile = $profile->businessProfile;
        $this->assertEquals('Cafe Barcelona', $businessProfile->name);
        $this->assertEquals('cafe', $businessProfile->business_type);
        $this->assertEquals($this->city->id, $businessProfile->city_id);
        $this->assertEquals('cafebarcelona', $businessProfile->instagram);
    }

    public function test_business_onboarding_accepts_primary_venue_and_city_name_fallback(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'business_type' => 'cafe',
                'city_name' => 'Barcelona',
                'phone_number' => '+34612345678',
                'primary_venue' => [
                    'name' => 'Cafe Barcelona Terrace',
                    'venue_type' => 'cafe',
                    'capacity' => 80,
                    'place_id' => 'terrace-place-id',
                    'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'latitude' => 41.3874,
                    'longitude' => 2.1686,
                    'photos' => [$this->tinyPngDataUri()],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.business_profile.city.name', 'Barcelona')
            ->assertJsonPath('data.business_profile.primary_venue.name', 'Cafe Barcelona Terrace')
            ->assertJsonPath('data.business_profile.primary_venue.capacity', 80);

        $profile->refresh();
        $profile->load('businessProfile');

        $this->assertEquals($this->city->id, $profile->businessProfile->city_id);
        $this->assertEquals('Cafe Barcelona Terrace', $profile->businessProfile->primary_venue['name']);
        $this->assertCount(1, $profile->businessProfile->primary_venue['photos']);
    }

    public function test_business_onboarding_persists_ordered_categories_and_preserves_primary_venue_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'categories' => ['cafe', 'coworking', 'other'],
                'city_id' => $this->city->id,
                'primary_venue' => [
                    'name' => 'Cafe Barcelona Terrace',
                    'venue_type' => 'cafe',
                    'capacity' => 80,
                    'place_id' => 'google-place-id',
                    'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'photos' => [],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.business_profile.business_type', 'cafe')
            ->assertJsonPath('data.business_profile.categories', ['cafe', 'coworking', 'other'])
            ->assertJsonPath('data.business_profile.primary_venue.formatted_address', 'Passeig de Gracia 1, Barcelona')
            ->assertJsonPath('data.business_profile.primary_venue.place_id', 'google-place-id');

        $profile->refresh();
        $profile->load('businessProfile');

        $this->assertSame('cafe', $profile->businessProfile->business_type);
        $this->assertSame(['cafe', 'coworking', 'other'], $profile->businessProfile->categories);
        $this->assertSame('Passeig de Gracia 1, Barcelona', $profile->businessProfile->primary_venue['formatted_address']);
        $this->assertSame('google-place-id', $profile->businessProfile->primary_venue['place_id']);
    }

    public function test_business_onboarding_product_path_without_venue_succeeds(): void
    {
        $other = City::factory()->create(['name' => 'Madrid']);

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Bean Brand',
                'business_type' => 'retail',
                'has_venue' => false,
                'city_id' => $this->city->id,
                'target_city_ids' => [$this->city->id, $other->id],
                'offering' => 'Single-origin coffee beans',
                'offer_photos' => [],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business_profile.has_venue', false)
            ->assertJsonPath('data.business_profile.offering', 'Single-origin coffee beans')
            ->assertJsonPath('data.business_profile.primary_venue', null);

        $profile->refresh();
        $profile->load('businessProfile');

        $this->assertFalse($profile->businessProfile->has_venue);
        $this->assertNull($profile->businessProfile->primary_venue);
        $this->assertEquals($this->city->id, $profile->businessProfile->city_id);
        $this->assertEquals(
            [$this->city->id, $other->id],
            $profile->businessProfile->target_city_ids
        );
    }

    public function test_business_onboarding_validates_categories_limit(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'categories' => ['cafe', 'coworking', 'other', 'gym'],
                'city_id' => $this->city->id,
                'primary_venue' => [
                    'name' => 'Cafe Barcelona Terrace',
                    'venue_type' => 'cafe',
                    'capacity' => 80,
                    'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'photos' => [],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['categories']);
    }

    public function test_business_onboarding_requires_primary_venue_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'primary_venue' => [
                    'photos' => [],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'primary_venue.name',
                'primary_venue.venue_type',
                'primary_venue.capacity',
                'primary_venue.formatted_address',
                'primary_venue.city',
            ]);
    }

    public function test_business_onboarding_rehosts_selected_google_photos_and_persists_imported_metadata(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        config()->set('services.google_places.api_key', 'test-key');
        Storage::fake('public');

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $googleGif = base64_decode($this->tinyGifBase64());
        $googleJpeg = base64_decode($this->tinyJpegBase64());
        $uploadedPng = base64_decode(substr($this->tinyPngDataUri(), strlen('data:image/png;base64,')));

        Http::fake([
            'places.googleapis.com/v1/places/google-place-id/photos/photo-2/media*' => Http::response([
                'name' => 'places/google-place-id/photos/photo-2/media',
                'photoUri' => 'https://google-photos.test/photo-2.gif',
            ]),
            'places.googleapis.com/v1/places/google-place-id/photos/photo-1/media*' => Http::response([
                'name' => 'places/google-place-id/photos/photo-1/media',
                'photoUri' => 'https://google-photos.test/photo-1.jpg',
            ]),
            'google-photos.test/photo-2.gif' => Http::response($googleGif, 200, ['Content-Type' => 'image/gif']),
            'google-photos.test/photo-1.jpg' => Http::response($googleJpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'about' => 'Imported summary from Google',
                'business_type' => 'cafe',
                'city_name' => 'Barcelona',
                'phone_number' => '+34612345678',
                'website' => 'https://cafebarcelona.com',
                'primary_venue' => [
                    'name' => 'Cafe Barcelona Terrace',
                    'venue_type' => 'cafe',
                    'capacity' => 80,
                    'place_id' => 'google-place-id',
                    'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'latitude' => 41.3874,
                    'longitude' => 2.1686,
                    'phone_number' => '+34612345678',
                    'website' => 'https://cafebarcelona.com',
                    'opening_hours' => ['Monday: 09:00-17:00'],
                    'description' => 'Imported summary from Google',
                    'price_level' => 'PRICE_LEVEL_MODERATE',
                    'rating' => 4.7,
                    'user_ratings_total' => 128,
                    'google_place_types' => ['cafe', 'food', 'establishment'],
                    'photos' => [
                        'places/google-place-id/photos/photo-2',
                        $this->tinyPngDataUri(),
                        'places/google-place-id/photos/photo-1',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business_profile.primary_venue.phone_number', '+34612345678')
            ->assertJsonPath('data.business_profile.primary_venue.website', 'https://cafebarcelona.com')
            ->assertJsonPath('data.business_profile.primary_venue.opening_hours.0', 'Monday: 09:00-17:00')
            ->assertJsonPath('data.business_profile.primary_venue.description', 'Imported summary from Google')
            ->assertJsonPath('data.business_profile.primary_venue.price_level', 'PRICE_LEVEL_MODERATE')
            ->assertJsonPath('data.business_profile.primary_venue.rating', 4.7)
            ->assertJsonPath('data.business_profile.primary_venue.user_ratings_total', 128)
            ->assertJsonPath('data.business_profile.primary_venue.google_place_types', ['cafe', 'food', 'establishment']);

        $profile->refresh();
        $profile->load('businessProfile');

        $savedVenue = $profile->businessProfile->primary_venue;

        $this->assertCount(3, $savedVenue['photos']);
        $this->assertSame('+34612345678', $savedVenue['phone_number']);
        $this->assertSame('https://cafebarcelona.com', $savedVenue['website']);
        $this->assertSame(['Monday: 09:00-17:00'], $savedVenue['opening_hours']);
        $this->assertSame('Imported summary from Google', $savedVenue['description']);
        $this->assertSame('PRICE_LEVEL_MODERATE', $savedVenue['price_level']);
        $this->assertSame(4.7, $savedVenue['rating']);
        $this->assertSame(128, $savedVenue['user_ratings_total']);
        $this->assertSame(['cafe', 'food', 'establishment'], $savedVenue['google_place_types']);

        $firstPhotoPath = $this->storagePathFromUrl($savedVenue['photos'][0]);
        $secondPhotoPath = $this->storagePathFromUrl($savedVenue['photos'][1]);
        $thirdPhotoPath = $this->storagePathFromUrl($savedVenue['photos'][2]);

        $this->assertSame($googleGif, Storage::disk('public')->get($firstPhotoPath));
        $this->assertSame($uploadedPng, Storage::disk('public')->get($secondPhotoPath));
        $this->assertSame($googleJpeg, Storage::disk('public')->get($thirdPhotoPath));
    }

    public function test_community_onboarding_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/onboarding/community', [
            'name' => 'Test Community',
            'community_type' => 'run_club',
            'city_id' => $this->city->id,
            'instagram' => 'testcommunity',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_community_onboarding_requires_community_user_type(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Test Community',
                'community_type' => 'run_club',
                'city_id' => $this->city->id,
                'instagram' => 'testcommunity',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_community_onboarding_validates_required_fields(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'community_type', 'city_id'],
            ]);
    }

    public function test_community_onboarding_validates_community_type(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Test User',
                'community_type' => 'invalid_type',
                'city_id' => $this->city->id,
                'instagram' => 'testuser',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['community_type'],
            ]);
    }

    public function test_community_onboarding_completes_successfully(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Maria Garcia',
                'about' => 'Food blogger and coffee enthusiast',
                'community_type' => 'run_club',
                'city_id' => $this->city->id,
                'phone_number' => '+34698765432',
                'instagram' => '@maria_food_bcn',
                'tiktok' => '@maria_food',
                'website' => 'https://mariafoodblog.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Community profile updated successfully')
            ->assertJsonPath('data.phone_number', '+34698765432')
            ->assertJsonPath('data.community_profile.name', 'Maria Garcia')
            ->assertJsonPath('data.community_profile.community_type', 'run_club')
            ->assertJsonPath('data.community_profile.instagram', 'maria_food_bcn')
            ->assertJsonPath('data.community_profile.tiktok', 'maria_food')
            ->assertJsonPath('data.community_profile.is_featured', false);

        // Verify database updates
        $profile->refresh();
        $this->assertEquals('+34698765432', $profile->phone_number);

        $communityProfile = $profile->communityProfile;
        $this->assertEquals('Maria Garcia', $communityProfile->name);
        $this->assertEquals('run_club', $communityProfile->community_type);
        $this->assertEquals($this->city->id, $communityProfile->city_id);

        // Onboarding auto-provisions the management community (member roster +
        // default tier + main chat) so the user never needs the manual form.
        $community = \App\Models\Community::query()
            ->where('owner_profile_id', $profile->id)
            ->first();
        $this->assertNotNull($community, 'Onboarding should auto-create a community.');
        $this->assertEquals('Maria Garcia', $community->name);
        $this->assertEquals($communityProfile->id, $community->community_profile_id);
        $this->assertTrue($community->is_primary);
        $this->assertTrue($community->tiers()->where('is_default', true)->exists());
    }

    public function test_community_onboarding_persists_community_size(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Maria Garcia',
                'community_type' => 'run_club',
                'community_size' => 320,
                'city_id' => $this->city->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.community_profile.community_size', 320);

        $profile->refresh();
        $this->assertEquals(320, $profile->communityProfile->community_size);
    }

    private function tinyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9oNcamcAAAAASUVORK5CYII=';
    }

    private function tinyGifBase64(): string
    {
        return 'R0lGODlhAQABAIABAP///wAAACwAAAAAAQABAAACAkQBADs=';
    }

    private function tinyJpegBase64(): string
    {
        return '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEBAPEA8QDw8PDw8PDw8QDw8QFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAgMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAB9gD/xAAXEAEBAQEAAAAAAAAAAAAAAAABEQAh/9oACAEBAAEFAjDVl//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8BP//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8BP//EABcQAAMBAAAAAAAAAAAAAAAAAAABESH/2gAIAQEABj8CjVf/xAAXEAEBAQEAAAAAAAAAAAAAAAABEQAh/9oACAEBAAE/IeY0i6P/2gAMAwEAAgADAAAAED//xAAVEQEBAAAAAAAAAAAAAAAAAAABEP/aAAgBAwEBPxBf/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPxBf/8QAFxABAQEBAAAAAAAAAAAAAAAAAREAITFhcf/aAAgBAQABPxA6L1rG9xUQX//Z';
    }

    private function storagePathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return ltrim(str_replace('/storage/', '', $path), '/');
    }
}
