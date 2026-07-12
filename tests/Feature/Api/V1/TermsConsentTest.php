<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TermsConsentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function registerAttendee(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/register/attendee', array_merge([
            'email' => 'attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accepted_terms' => true,
        ], $overrides));
    }

    public function test_attendee_register_requires_accepted_terms(): void
    {
        $response = $this->registerAttendee(['accepted_terms' => null]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);
    }

    public function test_register_rejects_unaccepted_terms(): void
    {
        $response = $this->registerAttendee(['accepted_terms' => false]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);
    }

    public function test_business_register_requires_accepted_terms(): void
    {
        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'biz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);
    }

    public function test_community_register_requires_accepted_terms(): void
    {
        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'community@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);
    }

    public function test_register_records_consent_version_and_timestamp(): void
    {
        $version = (string) config('legal.terms_version');

        $this->registerAttendee()->assertStatus(201);

        $profile = Profile::query()->where('email', 'attendee@example.com')->firstOrFail();

        $this->assertSame($version, $profile->terms_version);
        $this->assertNotNull($profile->terms_accepted_at);
    }

    public function test_me_reports_terms_status_after_register(): void
    {
        $version = (string) config('legal.terms_version');
        $token = $this->registerAttendee()->json('data.token');

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.terms.current_version', $version)
            ->assertJsonPath('data.terms.accepted_version', $version)
            ->assertJsonPath('data.terms.needs_acceptance', false);
    }

    public function test_me_needs_acceptance_after_version_bump(): void
    {
        $token = $this->registerAttendee()->json('data.token');

        config()->set('legal.terms_version', '2099-01-01');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.terms.current_version', '2099-01-01')
            ->assertJsonPath('data.terms.needs_acceptance', true);
    }

    public function test_consent_endpoint_records_current_version(): void
    {
        $token = $this->registerAttendee()->json('data.token');

        config()->set('legal.terms_version', '2099-01-01');

        $this->withToken($token)->postJson('/api/v1/me/consent')
            ->assertOk()
            ->assertJsonPath('data.terms.accepted_version', '2099-01-01')
            ->assertJsonPath('data.terms.needs_acceptance', false);

        $profile = Profile::query()->where('email', 'attendee@example.com')->firstOrFail();
        $this->assertSame('2099-01-01', $profile->terms_version);
    }

    public function test_google_signup_records_consent(): void
    {
        $version = (string) config('legal.terms_version');

        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->once()
                ->andReturn([
                    'google_id' => 'google-consent-1',
                    'email' => 'googlenew@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'user_type' => 'community',
        ])->assertOk()->assertJsonPath('data.user.terms.needs_acceptance', false);

        $profile = Profile::query()->where('email', 'googlenew@example.com')->firstOrFail();
        $this->assertSame($version, $profile->terms_version);
        $this->assertNotNull($profile->terms_accepted_at);
    }
}
