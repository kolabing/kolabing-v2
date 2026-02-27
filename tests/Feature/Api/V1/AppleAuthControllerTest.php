<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\AppleAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AppleAuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_apple_login_requires_identity_token(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['identity_token'],
            ]);
    }

    public function test_apple_login_returns_error_for_invalid_token(): void
    {
        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->with('invalid-token')
                ->andReturn(null);
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid Apple identity token')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['identity_token'],
            ]);
    }

    public function test_apple_login_returns_404_when_account_not_found(): void
    {
        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-nonexistent',
                    'email' => 'nonexistent@example.com',
                ]);
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No account found with this Apple ID. Please register first.')
            ->assertJsonPath('errors', null);
    }

    public function test_apple_login_succeeds_for_existing_business_user_by_apple_id(): void
    {
        $profile = Profile::factory()->business()->create([
            'apple_id' => 'apple-user-123',
            'google_id' => null,
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-user-123',
                    'email' => null,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $profile->email)
            ->assertJsonPath('data.user.user_type', 'business')
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
                        'onboarding_completed',
                    ],
                ],
            ]);
    }

    public function test_apple_login_succeeds_for_existing_user_by_email(): void
    {
        $profile = Profile::factory()->community()->create([
            'email' => 'community@example.com',
            'apple_id' => null,
            'google_id' => null,
        ]);
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-new-sub',
                    'email' => 'community@example.com',
                ]);
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.user.email', 'community@example.com')
            ->assertJsonPath('data.user.user_type', 'community');

        // Apple ID should be linked to the profile after first Apple login
        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'apple_id' => 'apple-new-sub',
        ]);
    }

    public function test_apple_login_name_field_is_optional(): void
    {
        $profile = Profile::factory()->business()->create([
            'apple_id' => 'apple-user-456',
            'google_id' => null,
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-user-456',
                    'email' => null,
                ]);
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
            'name' => 'John Doe',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_apple_login_token_can_be_used_for_authenticated_requests(): void
    {
        $profile = Profile::factory()->business()->create([
            'apple_id' => 'apple-user-789',
            'google_id' => null,
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-user-789',
                    'email' => null,
                ]);
        });

        $loginResponse = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
        ]);

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $profile->email);
    }
}
