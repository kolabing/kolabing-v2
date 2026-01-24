<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            ->assertJsonPath('data.user.onboarding_completed', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'is_new_user',
                    'user' => [
                        'id',
                        'email',
                        'phone_number',
                        'user_type',
                        'avatar_url',
                        'email_verified_at',
                        'onboarding_completed',
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
                    'onboarding_completed',
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
}
