<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\GoogleAuthService;
use Database\Seeders\RealisticDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_google_login_requires_id_token(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'user_type' => 'business',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['id_token'],
            ]);
    }

    public function test_google_login_requires_user_type(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['user_type'],
            ]);
    }

    public function test_google_login_validates_user_type_enum(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake-token',
            'user_type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['user_type'],
            ]);
    }

    public function test_google_login_returns_error_for_invalid_token(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->with('invalid-token')
                ->andReturn(null);
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'invalid-token',
            'user_type' => 'business',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid Google ID token')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['id_token'],
            ]);
    }

    public function test_google_login_creates_new_business_user(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-123',
                    'email' => 'newbusiness@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'business',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'newbusiness@example.com')
            ->assertJsonPath('data.user.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'is_new_user',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                        'business_profile',
                        'subscription',
                    ],
                ],
            ]);

        // Verify records were created
        $this->assertDatabaseHas('profiles', [
            'email' => 'newbusiness@example.com',
            'google_id' => 'google-123',
            'user_type' => 'business',
        ]);

        $profile = Profile::where('email', 'newbusiness@example.com')->first();
        $this->assertNotNull($profile);
        $this->assertDatabaseHas('business_profiles', [
            'profile_id' => $profile->id,
        ]);
        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'status' => 'inactive',
        ]);
    }

    public function test_google_login_creates_new_community_user(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-456',
                    'email' => 'newcommunity@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'community',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.user_type', 'community')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'is_new_user',
                    'user' => [
                        'id',
                        'email',
                        'user_type',
                        'community_profile',
                    ],
                ],
            ]);

        // Verify records were created
        $this->assertDatabaseHas('profiles', [
            'email' => 'newcommunity@example.com',
            'user_type' => 'community',
        ]);

        $profile = Profile::where('email', 'newcommunity@example.com')->first();
        $this->assertDatabaseHas('community_profiles', [
            'profile_id' => $profile->id,
        ]);

        // Community users should not have subscriptions
        $this->assertDatabaseMissing('business_subscriptions', [
            'profile_id' => $profile->id,
        ]);
    }

    public function test_google_login_returns_existing_user(): void
    {
        // Create existing user
        $profile = Profile::factory()->business()->create([
            'email' => 'existing@example.com',
            'google_id' => 'google-existing',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-existing',
                    'email' => 'existing@example.com',
                    'avatar_url' => 'https://example.com/new-avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'business',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.email', 'existing@example.com');
    }

    public function test_google_login_returns_conflict_for_user_type_mismatch(): void
    {
        // Create existing business user
        $profile = Profile::factory()->business()->create([
            'email' => 'existing@example.com',
            'google_id' => 'google-existing',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-existing',
                    'email' => 'existing@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        // Try to login as community
        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'community',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'User type mismatch')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['user_type'],
            ]);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_me_endpoint_returns_business_user_profile(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'phone_number',
                    'user_type',
                    'avatar_url',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                    'business_profile',
                    'subscription',
                ],
            ]);
    }

    public function test_me_endpoint_returns_community_user_profile(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_type', 'community')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'user_type',
                    'community_profile',
                ],
            ]);
    }

    public function test_logout_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_logout_endpoint_revokes_token(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $token = $profile->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully');

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_logout_endpoint_revokes_mobile_refresh_tokens(): void
    {
        $city = City::factory()->create();

        $login = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'logout-refresh@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Logout Refresh Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Logout Refresh Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $login->assertCreated();

        $logout = $this->withHeader('Authorization', 'Bearer '.$login->json('data.token'))
            ->postJson('/api/v1/auth/logout');

        $logout->assertOk()
            ->assertJsonPath('success', true);

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('data.refresh_token'),
        ]);

        $refresh->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid refresh token');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Registration Tests
    |--------------------------------------------------------------------------
    */

    public function test_register_business_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register/business', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => fake()->uuid(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_register_business_requires_unique_email(): void
    {
        $city = City::factory()->create();
        $existingProfile = Profile::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', 'This email is already registered');
    }

    public function test_register_business_requires_password_min_8_characters(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password'],
            ]);
    }

    public function test_register_business_requires_password_confirmation(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password'],
            ]);
    }

    public function test_register_business_validates_business_type(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'invalid_type',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['business_type'],
            ]);
    }

    public function test_register_business_validates_city_exists(): void
    {
        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
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

    public function test_register_business_creates_user_successfully(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'newbusiness@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'about' => 'A test business description',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'phone_number' => '+34612345678',
            'instagram' => '@testbusiness',
            'website' => 'https://testbusiness.com',
            'primary_venue' => [
                'name' => 'Test Business Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'newbusiness@example.com')
            ->assertJsonPath('data.user.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'is_new_user',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                        'business_profile',
                        'subscription',
                    ],
                ],
            ]);

        // Verify profile was created
        $this->assertDatabaseHas('profiles', [
            'email' => 'newbusiness@example.com',
            'user_type' => 'business',
            'phone_number' => '+34612345678',
        ]);

        $profile = Profile::where('email', 'newbusiness@example.com')->first();
        $this->assertNotNull($profile);
        $this->assertNotNull($profile->password);
        $this->assertTrue(Hash::check('password123', $profile->password));

        // Verify business profile was created with all data
        $this->assertDatabaseHas('business_profiles', [
            'profile_id' => $profile->id,
            'name' => 'Test Business',
            'about' => 'A test business description',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'instagram' => '@testbusiness',
            'website' => 'https://testbusiness.com',
        ]);

        // Verify inactive subscription was created
        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'status' => 'inactive',
        ]);
    }

    public function test_register_product_business_without_primary_venue_succeeds(): void
    {
        $cityA = City::factory()->create(['name' => 'Madrid', 'country' => 'Spain']);
        $cityB = City::factory()->create(['name' => 'Valencia', 'country' => 'Spain']);

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'productbiz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'about' => 'Specialty coffee beans shipped nationwide',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $cityA->id,
            'target_city_ids' => [$cityA->id, $cityB->id],
            'offering' => 'Single-origin coffee beans and brewing gear',
            'offer_photos' => [],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', 'business')
            ->assertJsonPath('data.user.business_profile.has_venue', false)
            ->assertJsonPath('data.user.business_profile.offering', 'Single-origin coffee beans and brewing gear')
            ->assertJsonPath('data.user.business_profile.primary_venue', null);

        $profile = Profile::where('email', 'productbiz@example.com')->first();
        $this->assertNotNull($profile);
        $profile->load('businessProfile');

        $this->assertFalse($profile->businessProfile->has_venue);
        $this->assertNull($profile->businessProfile->primary_venue);
        $this->assertEquals($cityA->id, $profile->businessProfile->city_id);
        $this->assertEquals(
            [$cityA->id, $cityB->id],
            $profile->businessProfile->target_city_ids
        );
        $this->assertEquals(
            'Single-origin coffee beans and brewing gear',
            $profile->businessProfile->offering
        );
    }

    public function test_register_product_business_auto_offer_uses_submitted_product_type(): void
    {
        $city = City::factory()->create(['name' => 'Madrid', 'country' => 'Spain']);

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'producttype@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'about' => 'Specialty coffee beans shipped nationwide',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $city->id,
            'offering' => 'Single-origin coffee beans',
            'product_type' => 'beverage',
        ]);

        $response->assertStatus(201);

        $profile = Profile::where('email', 'producttype@example.com')->first();
        $this->assertNotNull($profile);

        // Submitted product_type is persisted on the business profile...
        $this->assertSame('beverage', $profile->businessProfile->product_type);

        // ...and reused by the auto-provisioned product-promotion kolab.
        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $profile->id,
            'intent_type' => 'product_promotion',
            'product_type' => 'beverage',
        ]);
    }

    public function test_register_product_business_defaults_product_type_to_other(): void
    {
        $city = City::factory()->create(['name' => 'Madrid', 'country' => 'Spain']);

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'defaultpt@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $city->id,
            'offering' => 'Single-origin coffee beans',
        ])->assertStatus(201);

        $profile = Profile::where('email', 'defaultpt@example.com')->first();
        $this->assertSame('other', $profile->businessProfile->product_type);
        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $profile->id,
            'intent_type' => 'product_promotion',
            'product_type' => 'other',
        ]);
    }

    public function test_register_product_business_requires_city_when_no_venue(): void
    {
        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'nocity@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'business_type' => 'retail',
            'has_venue' => false,
            'offering' => 'Coffee beans',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_register_business_accepts_city_name_fallback_and_primary_venue(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $city = City::factory()->create([
            'name' => 'Barcelona',
            'country' => 'Spain',
        ]);

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'venuebusiness@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Sol Studio',
            'business_type' => 'cafe',
            'city_name' => 'Barcelona',
            'phone_number' => '+34612345678',
            'instagram' => 'solstudio',
            'website' => 'https://solstudio.com',
            'primary_venue' => [
                'name' => 'Sol Studio Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 120,
                'place_id' => 'google-place-id',
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'latitude' => 41.3874,
                'longitude' => 2.1686,
                'photos' => [
                    $this->tinyPngDataUri(),
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.business_profile.city.name', 'Barcelona')
            ->assertJsonPath('data.user.business_profile.primary_venue.name', 'Sol Studio Rooftop')
            ->assertJsonPath('data.user.business_profile.primary_venue.formatted_address', 'Carrer de Mallorca 1, Barcelona');

        $this->assertStringContainsString(
            'gallery/',
            (string) $response->json('data.user.business_profile.primary_venue.photos.0')
        );

        $profile = Profile::where('email', 'venuebusiness@example.com')->first();
        $this->assertNotNull($profile);

        $profile->load('businessProfile');
        $this->assertEquals($city->id, $profile->businessProfile->city_id);
        $this->assertEquals('Sol Studio Rooftop', $profile->businessProfile->primary_venue['name']);
        $this->assertCount(1, $profile->businessProfile->primary_venue['photos']);
    }

    public function test_register_business_requires_primary_venue_fields(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'invalidvenue@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'capacity' => 120,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'primary_venue.name',
                'primary_venue.venue_type',
                'primary_venue.formatted_address',
                'primary_venue.city',
            ]);
    }

    public function test_register_business_surfaces_nested_primary_venue_photo_url_errors(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'invalid-photo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Test Business Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => ['not-a-valid-url'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['primary_venue.photos.0']);
    }

    public function test_register_business_accepts_ordered_categories(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'multicategory@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Multi Category Business',
            'categories' => ['coworking', 'cafe', 'other'],
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Shared Clubhouse',
                'venue_type' => 'coworking',
                'capacity' => 120,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.business_profile.business_type', 'coworking')
            ->assertJsonPath('data.user.business_profile.categories', ['coworking', 'cafe', 'other']);
    }

    /*
    |--------------------------------------------------------------------------
    | Community Registration Tests
    |--------------------------------------------------------------------------
    */

    public function test_register_community_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register/community', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Community',
            'community_type' => 'run_club',
            'city_id' => fake()->uuid(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_register_community_validates_community_type(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Community',
            'community_type' => 'invalid_type',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['community_type'],
            ]);
    }

    public function test_register_community_persists_community_size(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'sizedcommunity@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Sized Community',
            'community_type' => 'run_club',
            'community_size' => 250,
            'city_id' => $city->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.community_profile.community_size', 250);

        $profile = Profile::where('email', 'sizedcommunity@example.com')->first();
        $this->assertNotNull($profile);
        $profile->load('communityProfile');
        $this->assertEquals(250, $profile->communityProfile->community_size);
    }

    public function test_register_community_creates_user_successfully(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'newcommunity@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Community',
            'about' => 'A test community description',
            'community_type' => 'run_club',
            'city_id' => $city->id,
            'phone_number' => '+34612345678',
            'instagram' => '@testcommunity',
            'tiktok' => '@testcommunity',
            'website' => 'https://testcommunity.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'newcommunity@example.com')
            ->assertJsonPath('data.user.user_type', 'community')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'is_new_user',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                        'community_profile',
                    ],
                ],
            ]);

        // Verify profile was created
        $this->assertDatabaseHas('profiles', [
            'email' => 'newcommunity@example.com',
            'user_type' => 'community',
            'phone_number' => '+34612345678',
        ]);

        $profile = Profile::where('email', 'newcommunity@example.com')->first();
        $this->assertNotNull($profile);
        $this->assertNotNull($profile->password);
        $this->assertTrue(Hash::check('password123', $profile->password));

        // Verify community profile was created with all data
        $this->assertDatabaseHas('community_profiles', [
            'profile_id' => $profile->id,
            'name' => 'Test Community',
            'about' => 'A test community description',
            'community_type' => 'run_club',
            'city_id' => $city->id,
            'instagram' => '@testcommunity',
            'tiktok' => '@testcommunity',
            'website' => 'https://testcommunity.com',
        ]);

        // Community users should not have subscriptions
        $this->assertDatabaseMissing('business_subscriptions', [
            'profile_id' => $profile->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Login Tests
    |--------------------------------------------------------------------------
    */

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_fails_for_google_only_user(): void
    {
        // Create a user with Google OAuth (no password)
        $profile = Profile::factory()->business()->create([
            'email' => 'googleuser@example.com',
            'google_id' => 'google-123',
            'password' => null,
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'googleuser@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This account uses Google Sign-In. Please login with Google.');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $city = City::factory()->create();

        // Create a user with email/password registration
        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'passworduser@example.com',
            'password' => 'correctpassword',
            'password_confirmation' => 'correctpassword',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Password User Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'passworduser@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $city = City::factory()->create();

        // Create a user with email/password registration
        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'validuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Valid User Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'validuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'validuser@example.com')
            ->assertJsonPath('data.user.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                        'business_profile',
                        'subscription',
                    ],
                ],
            ]);

        // Verify the token works
        $token = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'validuser@example.com');
    }

    public function test_login_succeeds_for_community_user(): void
    {
        $city = City::factory()->create();

        // Create a community user with email/password registration
        $this->postJson('/api/v1/auth/register/community', [
            'email' => 'communityuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Community',
            'community_type' => 'run_club',
            'city_id' => $city->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'communityuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.user.email', 'communityuser@example.com')
            ->assertJsonPath('data.user.user_type', 'community')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'user' => [
                        'id',
                        'email',
                        'user_type',
                        'community_profile',
                    ],
                ],
            ]);
    }

    public function test_new_login_revokes_previous_mobile_token_pair_for_the_same_account(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'rotating-login@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Rotating Login Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Rotating Login Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertCreated();

        $firstLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'rotating-login@example.com',
            'password' => 'password123',
        ]);

        $firstLogin->assertOk()
            ->assertJsonPath('success', true);

        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'rotating-login@example.com',
            'password' => 'password123',
        ]);

        $secondLogin->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotSame($firstLogin->json('data.token'), $secondLogin->json('data.token'));
        $this->assertNotSame($firstLogin->json('data.refresh_token'), $secondLogin->json('data.refresh_token'));

        $staleProtectedRequest = $this->withHeader('Authorization', 'Bearer '.$firstLogin->json('data.token'))
            ->getJson('/api/v1/auth/me');

        $staleProtectedRequest->assertStatus(401)
            ->assertJsonPath('success', false);

        $staleRefresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $firstLogin->json('data.refresh_token'),
        ]);

        $staleRefresh->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid refresh token');

        $freshProtectedRequest = $this->withHeader('Authorization', 'Bearer '.$secondLogin->json('data.token'))
            ->getJson('/api/v1/auth/me');

        $freshProtectedRequest->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'rotating-login@example.com');
    }

    public function test_refresh_returns_new_access_token_and_complete_user_payload(): void
    {
        $city = City::factory()->create();

        $login = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'refreshable@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Refreshable Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Refreshable Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ]);

        $login->assertCreated();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('data.refresh_token'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Token refreshed successfully')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'refreshable@example.com')
            ->assertJsonPath('data.user.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'refresh_token',
                    'refresh_token_expires_at',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                        'business_profile',
                        'subscription',
                    ],
                ],
            ]);

        $this->assertNotSame(
            $login->json('data.token'),
            $response->json('data.token')
        );
        $this->assertNotSame(
            $login->json('data.refresh_token'),
            $response->json('data.refresh_token')
        );
    }

    public function test_login_token_can_access_protected_endpoint_immediately_after_login(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'fresh-login@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Fresh Login Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Fresh Login Venue',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'fresh-login@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true);

        $me = $this->withHeader('Authorization', 'Bearer '.$login->json('data.token'))
            ->getJson('/api/v1/auth/me');

        $me->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'fresh-login@example.com');
    }

    public function test_seeded_profile_has_password_credentials_for_login_flow(): void
    {
        $this->seed(RealisticDataSeeder::class);

        $profile = Profile::query()->firstOrFail();

        $this->assertNotNull($profile->password);
        $this->assertTrue(Hash::check('password123', $profile->password));
    }

    // ── Register path auto-provisioning ─────────────────────────────────────
    // The app registers business/community accounts in ONE SHOT via these
    // endpoints (it never calls PUT /onboarding/{business,community} for these
    // roles), so the same auto-provisions that fire on onboarding-complete must
    // fire here too, using the shared OnboardingService logic.

    public function test_register_community_auto_creates_one_primary_community(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/community', [
            'email' => 'autocomm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Auto Run Club',
            'community_type' => 'run_club',
            'city_id' => $city->id,
        ])->assertStatus(201);

        $profile = Profile::where('email', 'autocomm@example.com')->firstOrFail();

        $communities = \App\Models\Community::query()
            ->where('owner_profile_id', $profile->id)
            ->get();

        $this->assertCount(1, $communities, 'Register should auto-create exactly one community.');
        $this->assertTrue((bool) $communities->first()->is_primary, 'Auto-created community must be primary.');
        $this->assertSame('Auto Run Club', $communities->first()->name);
    }

    public function test_register_business_product_path_auto_creates_one_published_product_kolab(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'autoprodbiz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'about' => 'Specialty coffee beans',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $city->id,
            'offering' => 'Single-origin beans',
            'offer_photos' => [],
        ])->assertStatus(201);

        $profile = Profile::where('email', 'autoprodbiz@example.com')->firstOrFail();

        $kolabs = \App\Models\Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->get();

        $this->assertCount(1, $kolabs, 'Business register should auto-create exactly one kolab.');
        $kolab = $kolabs->first();
        $this->assertSame(\App\Enums\IntentType::ProductPromotion, $kolab->intent_type);
        $this->assertNotNull($kolab->published_at, 'Auto-offer must be published live.');
    }

    public function test_register_business_venue_path_auto_creates_one_published_venue_kolab(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'autovenuebiz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Cafe Barcelona',
            'about' => 'A cozy cafe',
            'business_type' => 'cafe',
            'has_venue' => true,
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Cafe Barcelona Terrace',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertStatus(201);

        $profile = Profile::where('email', 'autovenuebiz@example.com')->firstOrFail();

        $kolabs = \App\Models\Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->get();

        $this->assertCount(1, $kolabs, 'Venue business register should auto-create exactly one kolab.');
        $kolab = $kolabs->first();
        $this->assertSame(\App\Enums\IntentType::VenuePromotion, $kolab->intent_type);
        $this->assertNotNull($kolab->published_at);
    }

    public function test_register_then_onboarding_does_not_double_create_community(): void
    {
        $city = City::factory()->create();

        $register = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'idemcomm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Idempotent Club',
            'community_type' => 'run_club',
            'city_id' => $city->id,
        ]);
        $register->assertStatus(201);

        $profile = Profile::where('email', 'idemcomm@example.com')->firstOrFail();

        // Now also hit the onboarding-complete endpoint (which runs the same
        // shared provision). It must NOT create a second community.
        $this->actingAs($profile)->putJson('/api/v1/onboarding/community', [
            'name' => 'Idempotent Club',
            'community_type' => 'run_club',
            'city_id' => $city->id,
        ])->assertStatus(200);

        $this->assertSame(
            1,
            \App\Models\Community::query()->where('owner_profile_id', $profile->id)->count(),
            'Register + onboarding must not create a second community.'
        );
    }

    public function test_register_then_onboarding_does_not_double_create_kolab(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'idembiz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bean Brand',
            'about' => 'Specialty coffee beans',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $city->id,
            'offering' => 'Single-origin beans',
            'offer_photos' => [],
        ])->assertStatus(201);

        $profile = Profile::where('email', 'idembiz@example.com')->firstOrFail();

        // Now also hit the onboarding-complete endpoint. It must NOT create a
        // second auto-offer.
        $this->actingAs($profile)->putJson('/api/v1/onboarding/business', [
            'name' => 'Bean Brand',
            'business_type' => 'retail',
            'has_venue' => false,
            'city_id' => $city->id,
            'offering' => 'Single-origin beans',
        ])->assertStatus(200);

        $this->assertSame(
            1,
            \App\Models\Kolab::query()->where('creator_profile_id', $profile->id)->count(),
            'Register + onboarding must not create a second auto-offer.'
        );
    }

    private function tinyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9oNcamcAAAAASUVORK5CYII=';
    }
}
