<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeeRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_register_as_attendee(): void
    {
        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['token', 'token_type', 'is_new_user', 'user'],
            ]);
    }

    public function test_attendee_profile_created_with_defaults(): void
    {
        $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $profile = Profile::query()->where('email', 'attendee@example.com')->first();
        $this->assertNotNull($profile);
        $this->assertSame(UserType::Attendee, $profile->user_type);

        $attendeeProfile = AttendeeProfile::query()->where('profile_id', $profile->id)->first();
        $this->assertNotNull($attendeeProfile);
        $this->assertSame(0, $attendeeProfile->total_points);
        $this->assertSame(0, $attendeeProfile->total_challenges_completed);
        $this->assertSame(0, $attendeeProfile->total_events_attended);
    }

    public function test_duplicate_email_returns_422(): void
    {
        Profile::factory()->attendee()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_short_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'attendee@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_missing_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
