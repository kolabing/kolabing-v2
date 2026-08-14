<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\MaintainerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintainerSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function configureMaintainer(?string $email, ?string $password, string $name = 'Ops Admin'): void
    {
        config()->set('admin.maintainer', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function test_it_provisions_a_maintainer_from_config(): void
    {
        $this->configureMaintainer('ops@kolabing.com', 'super-secret-pw');

        $this->seed(MaintainerSeeder::class);

        $user = User::query()->where('email', 'ops@kolabing.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isMaintainer());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('super-secret-pw', $user->password));
    }

    public function test_it_promotes_and_resets_password_for_an_existing_user(): void
    {
        User::factory()->create([
            'email' => 'ops@kolabing.com',
            'is_maintainer' => false,
        ]);

        $this->configureMaintainer('ops@kolabing.com', 'rotated-pw');

        $this->seed(MaintainerSeeder::class);

        $user = User::query()->where('email', 'ops@kolabing.com')->first();

        $this->assertTrue($user->isMaintainer());
        $this->assertTrue(Hash::check('rotated-pw', $user->password));
        $this->assertSame(1, User::query()->where('email', 'ops@kolabing.com')->count());
    }

    public function test_it_no_ops_when_credentials_are_not_configured(): void
    {
        $this->configureMaintainer(null, null);

        $this->seed(MaintainerSeeder::class);

        $this->assertSame(0, User::query()->where('is_maintainer', true)->count());
    }

    public function test_a_provisioned_maintainer_can_log_into_the_admin_panel(): void
    {
        $this->configureMaintainer('ops@kolabing.com', 'login-pw-123');
        $this->seed(MaintainerSeeder::class);

        $response = $this->post('/admin/login', [
            'email' => 'ops@kolabing.com',
            'password' => 'login-pw-123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs(
            User::query()->where('email', 'ops@kolabing.com')->first(),
            'admin',
        );
    }
}
