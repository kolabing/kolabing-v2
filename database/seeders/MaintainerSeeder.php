<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Provisions (or promotes + password-resets) the admin-panel maintainer from
 * config/admin.php (ADMIN_MAINTAINER_* env). Idempotent via updateOrCreate on
 * email. No-ops when the email or password is not configured, so it is safe to
 * run in dev / CI and safe to leave wired into a deploy command.
 *
 * Prod (Laravel Cloud): set ADMIN_MAINTAINER_EMAIL + ADMIN_MAINTAINER_PASSWORD
 * (+ optionally ADMIN_MAINTAINER_NAME), then run
 *   php artisan db:seed --class=MaintainerSeeder --force
 * in the Commands tab (or append it to the deploy command to self-heal).
 * See docs/admin-access.md.
 *
 * NOTE: intentionally NOT registered in DatabaseSeeder, so a full `db:seed`
 * (which also runs RealisticDataSeeder) is never required to provision admin
 * access. Always run it in isolation with --class=MaintainerSeeder.
 */
class MaintainerSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.maintainer.email');
        $password = config('admin.maintainer.password');
        $name = config('admin.maintainer.name') ?: 'Kolabing Maintainer';

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'MaintainerSeeder skipped: ADMIN_MAINTAINER_EMAIL / ADMIN_MAINTAINER_PASSWORD not set.'
            );

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password, // hashed by the User `password` cast
                'is_maintainer' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Maintainer ready: {$user->email}");
    }
}
