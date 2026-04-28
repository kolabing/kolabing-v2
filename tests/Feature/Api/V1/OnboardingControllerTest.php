<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            ->assertJsonPath('data.onboarding_completed', true)
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
            ->assertJsonPath('data.onboarding_completed', true)
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
    }

    private function tinyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9oNcamcAAAAASUVORK5CYII=';
    }
}
