<?php

declare(strict_types=1);

use App\Models\Challenge;
use Database\Seeders\SystemChallengeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
 * kolabing-v2#49) — only 18 of the ~49 seeded missions are visible in the app;
 * the rest stay seeded for admin visibility/future activation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // Wrap the destructive wipe and the reseed in a single transaction: if
        // the seeder throws partway (slug collision, DB hiccup, enum overflow),
        // the delete rolls back too, so the system-challenge catalogue is never
        // left empty or half-seeded in production.
        DB::transaction(function (): void {
            Challenge::query()->where('is_system', true)->delete();

            Artisan::call('db:seed', [
                '--class' => SystemChallengeSeeder::class,
                '--force' => true,
            ]);
        });
    }

    public function down(): void
    {
        // No-op: the old icebreaker catalogue is intentionally not restored.
    }
};
