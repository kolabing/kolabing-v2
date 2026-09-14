<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Reproduces Daniel's reported symptom (2026-09-14): submitting the login form
 * lands back on /admin/login with no visible error and no successful redirect.
 * Every code path in AuthController::store() either shows a flashed error or
 * redirects away to the dashboard -- so "same page, nothing" should be
 * impossible by the code as written. This drives the REAL POST /admin/login
 * route (not actingAs(), which bypasses the controller's session lifecycle
 * entirely) for both branches that call session()->regenerate()/invalidate().
 */
class LoginSessionRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_correct_password_but_not_a_maintainer_shows_the_specific_error(): void
    {
        $user = User::factory()->create([
            'email' => 'notmaintainer@kolabing.com',
            'password' => 'correct-password-123',
            'is_maintainer' => false,
        ]);

        $response = $this->withHeader('Referer', url('/admin/login'))->post('/admin/login', [
            'email' => 'notmaintainer@kolabing.com',
            'password' => 'correct-password-123',
        ]);

        $response->assertRedirect('/admin/login');

        $followUp = $this->get('/admin/login');
        $followUp->assertSee('This account is not allowed to access the admin panel', false);

        $this->assertGuest('admin');
    }

    public function test_correct_password_and_maintainer_actually_logs_in(): void
    {
        $user = User::factory()->create([
            'email' => 'realmaintainer@kolabing.com',
            'password' => 'correct-password-123',
            'is_maintainer' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'realmaintainer@kolabing.com',
            'password' => 'correct-password-123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($user, 'admin');

        $dashboard = $this->get(route('admin.users.index'));
        $dashboard->assertOk();
    }
}
