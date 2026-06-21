<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\BusinessProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reset_password_page_renders_with_token_and_email(): void
    {
        $response = $this->get('/reset-password?token=abc123&email='.urlencode('user@example.com'));

        $response->assertOk()
            ->assertViewIs('auth.reset-password')
            ->assertSee('user@example.com')
            ->assertSee('abc123');
    }

    public function test_reset_password_updates_password_with_valid_token(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'reset-web@example.com',
            'password' => 'old-password-123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $token = Password::broker()->createToken($profile);
        $url = '/reset-password?token='.$token.'&email='.urlencode($profile->email);

        $response = $this->from($url)->post('/reset-password', [
            'token' => $token,
            'email' => $profile->email,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertRedirect(route('password.reset'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password-456', $profile->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $profile = Profile::factory()->business()->create([
            'email' => 'reset-web2@example.com',
            'password' => 'old-password-123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $url = '/reset-password?token=invalid-token&email='.urlencode($profile->email);

        $response = $this->from($url)->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $profile->email,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertRedirect($url);
        $response->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password-123', $profile->fresh()->password));
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $url = '/reset-password?token=abc&email='.urlencode('user@example.com');

        $response = $this->from($url)->post('/reset-password', [
            'token' => 'abc',
            'email' => 'user@example.com',
            'password' => 'new-password-456',
            'password_confirmation' => 'different-456',
        ]);

        $response->assertRedirect($url);
        $response->assertSessionHasErrors('password');
    }
}
