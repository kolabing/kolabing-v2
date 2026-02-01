<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Forgot Password Tests
    |--------------------------------------------------------------------------
    */

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $profile = Profile::factory()->business()->create([
            'email' => 'reset@example.com',
            'password' => 'password123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset link sent to your email.');

        Notification::assertSentTo($profile, ResetPassword::class);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_forgot_password_returns_error_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_forgot_password_works_for_business_user(): void
    {
        Notification::fake();

        $profile = Profile::factory()->business()->create([
            'email' => 'business-reset@example.com',
            'password' => 'password123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'business-reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Notification::assertSentTo($profile, ResetPassword::class);
    }

    public function test_forgot_password_works_for_community_user(): void
    {
        Notification::fake();

        $profile = Profile::factory()->community()->create([
            'email' => 'community-reset@example.com',
            'password' => 'password123',
        ]);
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'community-reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Notification::assertSentTo($profile, ResetPassword::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password Tests
    |--------------------------------------------------------------------------
    */

    public function test_reset_password_with_valid_token(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'valid-reset@example.com',
            'password' => 'oldpassword123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $token = Password::broker()->createToken($profile);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'valid-reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password has been reset successfully.');

        // Verify password was changed
        $profile->refresh();
        $this->assertTrue(Hash::check('newpassword123', $profile->password));
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['token', 'email', 'password'],
            ]);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password'],
            ]);
    }

    public function test_reset_password_requires_minimum_8_chars(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password'],
            ]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'invalid-token@example.com',
            'password' => 'oldpassword123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-value',
            'email' => 'invalid-token@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        // Verify password was not changed
        $profile->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $profile->password));
    }

    public function test_reset_password_fails_with_wrong_email(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'correct@example.com',
            'password' => 'oldpassword123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $token = Password::broker()->createToken($profile);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'wrong@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        // Verify password was not changed
        $profile->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $profile->password));
    }

    public function test_reset_password_revokes_all_tokens(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'revoke-tokens@example.com',
            'password' => 'oldpassword123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        // Create multiple tokens
        $profile->createToken('token-1');
        $profile->createToken('token-2');
        $profile->createToken('token-3');

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $token = Password::broker()->createToken($profile);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'revoke-tokens@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify all tokens were revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
