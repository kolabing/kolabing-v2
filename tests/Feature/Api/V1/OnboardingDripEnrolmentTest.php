<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserType;
use App\Models\City;
use App\Models\Profile;
use App\Services\AppleAuthService;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Every registration seam must enrol the new profile into the onboarding drip
 * (AuthService::startOnboardingDrip -> OnboardingDripService::startForProfile),
 * so the scheduled `app:send-onboarding-drip` command has state to act on
 * without the one-off --sync-new backfill.
 */
class OnboardingDripEnrolmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function assertEnrolledAtStepZero(string $email): void
    {
        $profile = Profile::query()->where('email', $email)->firstOrFail();

        $this->assertDatabaseHas('onboarding_drip_states', [
            'profile_id' => $profile->id,
            'next_sequence' => 0,
            'cancelled_at' => null,
        ]);
    }

    public function test_business_email_registration_enrols_in_drip(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'drip-business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Drip Business',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Drip Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 50,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertStatus(201);

        $this->assertEnrolledAtStepZero('drip-business@example.com');
    }

    public function test_community_email_registration_enrols_in_drip(): void
    {
        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/community', [
            'accepted_terms' => true,
            'email' => 'drip-community@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Drip Community',
            'community_type' => 'run_club',
            'city_id' => $city->id,
        ])->assertStatus(201);

        $this->assertEnrolledAtStepZero('drip-community@example.com');
    }

    public function test_attendee_email_registration_enrols_in_drip(): void
    {
        $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'drip-attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accepted_terms' => true,
        ])->assertStatus(201);

        $this->assertEnrolledAtStepZero('drip-attendee@example.com');
    }

    public function test_google_registration_enrols_in_drip(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-drip-1',
                    'email' => 'drip-google@example.com',
                    'avatar_url' => null,
                    'email_verified' => true,
                ]);
        });

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'business',
        ])->assertStatus(200);

        $this->assertEnrolledAtStepZero('drip-google@example.com');
    }

    public function test_apple_registration_enrols_in_drip(): void
    {
        $this->mock(AppleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdentityToken')
                ->once()
                ->andReturn([
                    'apple_id' => 'apple-drip-1',
                    'email' => 'drip-apple@example.com',
                ]);
        });

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'valid-token',
            'name' => 'Ada Lovelace',
            'user_type' => 'attendee',
        ])->assertStatus(200);

        $this->assertEnrolledAtStepZero('drip-apple@example.com');
    }

    public function test_drip_enrolment_failure_does_not_break_registration(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-drip-2',
                    'email' => 'drip-resilient@example.com',
                    'avatar_url' => null,
                    'email_verified' => true,
                ]);
        });

        $this->mock(\App\Services\OnboardingDripService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('startForProfile')
                ->once()
                ->andThrow(new \RuntimeException('drip write failed'));
        });

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'community',
        ])->assertStatus(200)
            ->assertJsonPath('data.is_new_user', true);

        $this->assertDatabaseHas('profiles', [
            'email' => 'drip-resilient@example.com',
            'user_type' => UserType::Community->value,
        ]);
    }
}
