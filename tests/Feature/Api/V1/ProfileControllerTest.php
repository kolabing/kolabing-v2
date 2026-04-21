<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Show Profile Tests
    |--------------------------------------------------------------------------
    */

    public function test_show_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/profile');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_show_profile_returns_business_user_with_subscription(): void
    {
        $city = City::factory()->create();
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Test Business',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Test Business Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 120,
                'place_id' => 'google-place-id',
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'latitude' => 41.3874,
                'longitude' => 2.1686,
                'photos' => ['https://example.com/venue-photo.jpg'],
            ],
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_type', 'business')
            ->assertJsonPath('data.business_profile.name', 'Test Business')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'phone_number',
                    'user_type',
                    'avatar_url',
                    'email_verified_at',
                    'onboarding_completed',
                    'created_at',
                    'updated_at',
                    'business_profile' => [
                        'id',
                        'name',
                        'about',
                        'business_type',
                        'city',
                        'instagram',
                        'website',
                        'profile_photo',
                        'primary_venue',
                    ],
                    'subscription' => [
                        'id',
                        'status',
                        'current_period_start',
                        'current_period_end',
                        'cancel_at_period_end',
                    ],
                ],
            ]);

        $response->assertJsonPath('data.business_profile.primary_venue.name', 'Test Business Rooftop')
            ->assertJsonPath('data.business_profile.primary_venue.photos.0', 'https://example.com/venue-photo.jpg');
    }

    public function test_show_profile_returns_community_user(): void
    {
        $city = City::factory()->create();
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Test Community',
            'city_id' => $city->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_type', 'community')
            ->assertJsonPath('data.community_profile.name', 'Test Community')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'phone_number',
                    'user_type',
                    'avatar_url',
                    'onboarding_completed',
                    'community_profile' => [
                        'id',
                        'name',
                        'about',
                        'community_type',
                        'city',
                        'instagram',
                        'tiktok',
                        'website',
                        'profile_photo',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Profile Tests
    |--------------------------------------------------------------------------
    */

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/me/profile', [
            'phone_number' => '+34600000000',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_update_business_profile_successfully(): void
    {
        $city = City::factory()->create();
        $newCity = City::factory()->create();
        $profile = Profile::factory()->business()->create([
            'phone_number' => '+34600000000',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Old Business Name',
            'city_id' => $city->id,
        ]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'phone_number' => '+34611111111',
                'name' => 'New Business Name',
                'about' => 'Updated about text',
                'city_id' => $newCity->id,
                'instagram' => '@newinstagram',
                'website' => 'https://newwebsite.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('data.phone_number', '+34611111111')
            ->assertJsonPath('data.business_profile.name', 'New Business Name')
            ->assertJsonPath('data.business_profile.about', 'Updated about text')
            ->assertJsonPath('data.business_profile.instagram', '@newinstagram')
            ->assertJsonPath('data.business_profile.website', 'https://newwebsite.com');

        // Verify database was updated
        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'phone_number' => '+34611111111',
        ]);

        $this->assertDatabaseHas('business_profiles', [
            'profile_id' => $profile->id,
            'name' => 'New Business Name',
            'city_id' => $newCity->id,
        ]);
    }

    public function test_update_community_profile_successfully(): void
    {
        $city = City::factory()->create();
        $newCity = City::factory()->create();
        $profile = Profile::factory()->community()->create([
            'phone_number' => '+34600000000',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Old Community Name',
            'city_id' => $city->id,
        ]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'phone_number' => '+34622222222',
                'name' => 'New Community Name',
                'about' => 'Updated community about',
                'city_id' => $newCity->id,
                'instagram' => '@communityinsta',
                'tiktok' => '@communitytiktok',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('data.phone_number', '+34622222222')
            ->assertJsonPath('data.community_profile.name', 'New Community Name')
            ->assertJsonPath('data.community_profile.tiktok', '@communitytiktok');

        // Verify database was updated
        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'phone_number' => '+34622222222',
        ]);

        $this->assertDatabaseHas('community_profiles', [
            'profile_id' => $profile->id,
            'name' => 'New Community Name',
        ]);
    }

    public function test_update_profile_validates_city_exists(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'city_id' => fake()->uuid(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['city_id'],
            ]);
    }

    public function test_update_profile_validates_website_url(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'website' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['website'],
            ]);
    }

    public function test_update_profile_allows_partial_updates(): void
    {
        $city = City::factory()->create();
        $profile = Profile::factory()->business()->create([
            'phone_number' => '+34600000000',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Original Name',
            'about' => 'Original About',
            'city_id' => $city->id,
        ]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'name' => 'Updated Name Only',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone_number', '+34600000000')
            ->assertJsonPath('data.business_profile.name', 'Updated Name Only')
            ->assertJsonPath('data.business_profile.about', 'Original About');
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Photo Upload Tests
    |--------------------------------------------------------------------------
    */

    public function test_business_user_can_upload_profile_photo(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'profile_photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully');

        $profile->refresh();
        $businessProfile = $profile->businessProfile;

        $this->assertNotNull($businessProfile->profile_photo);
        $this->assertStringContainsString('profiles/', $businessProfile->profile_photo);
    }

    public function test_community_user_can_upload_profile_photo(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'profile_photo' => UploadedFile::fake()->image('avatar.png', 400, 400),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $profile->refresh();
        $communityProfile = $profile->communityProfile;

        $this->assertNotNull($communityProfile->profile_photo);
        $this->assertStringContainsString('profiles/', $communityProfile->profile_photo);
    }

    public function test_profile_photo_upload_rejects_non_image_file(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'profile_photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_photo']);
    }

    public function test_profile_photo_upload_rejects_file_exceeding_5mb(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'profile_photo' => UploadedFile::fake()->image('large.jpg')->size(6000),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_photo']);
    }

    public function test_profile_photo_upload_with_other_fields(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $city = City::factory()->create();
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Old Name',
            'city_id' => $city->id,
        ]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'name' => 'New Name',
                'profile_photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business_profile.name', 'New Name');

        $profile->refresh();
        $this->assertNotNull($profile->businessProfile->profile_photo);
    }

    public function test_profile_photo_is_optional_on_update(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'profile_photo' => 'https://example.com/old-photo.jpg',
        ]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/profile', [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Old photo URL should remain unchanged
        $profile->refresh();
        $this->assertEquals('https://example.com/old-photo.jpg', $profile->businessProfile->profile_photo);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Account Tests
    |--------------------------------------------------------------------------
    */

    public function test_delete_account_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/me/account');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_delete_account_soft_deletes_profile(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);
        NotificationPreference::factory()->create(['profile_id' => $profile->id]);

        // Create a token to verify it gets revoked
        $token = $profile->createToken('test-token');

        $response = $this->actingAs($profile)
            ->deleteJson('/api/v1/me/account');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully');

        // Verify profile is soft deleted
        $this->assertSoftDeleted('profiles', [
            'id' => $profile->id,
        ]);

        // Verify token was revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_deleted_account_cannot_login(): void
    {
        $city = City::factory()->create();

        // Register a user
        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'deleteme@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Delete Me Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $profile = Profile::where('email', 'deleteme@example.com')->first();

        // Delete the account
        $this->actingAs($profile)
            ->deleteJson('/api/v1/me/account')
            ->assertStatus(200);

        // Try to login - should fail
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'deleteme@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
