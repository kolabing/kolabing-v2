<?php

declare(strict_types=1);

use App\Models\Challenge;
use Database\Seeders\SystemChallengeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Replace the old peer-verified system icebreakers with the new self-tracked
 * MISSION set. Production auto-deploy runs `migrate` (not `db:seed`), so the
 * mission catalogue has to land via a migration.
 *
 * Deleting system challenges cascades their completions / collaboration links /
 * defaults — accepted, pre-launch (see the gamification plan). Tests manage
 * their own data and assert on counts, so skip there.
 *
 * Idempotent: the seeder is keyed on `slug`, so re-running only upserts.
 *
 * The seeder also sets `app_visible` per mission (Maria's curated v1 app set,
 * kolabing-v2#49) — only ~17 of the ~49 seeded missions are visible in the app;
 * the rest stay seeded for admin visibility/future activation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Challenge::query()->where('is_system', true)->delete();

        Artisan::call('db:seed', [
            '--class' => SystemChallengeSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // No-op: the old icebreaker catalogue is intentionally not restored.
    }
};
