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
