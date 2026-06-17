<?php

use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CommunityTypeSeeder;
use Database\Seeders\IconSeeder;
use Database\Seeders\OfferOptionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Reference/taxonomy data lives in seeders, but production auto-deploy runs
 * `migrate` ONLY (not `db:seed`) — so offer_options, the icon library, and the
 * business-type facets (applies_to / icon) were empty in the admin dashboard on
 * prod even though they worked locally (where we ran the seeders by hand).
 *
 * These seeders are idempotent (updateOrCreate keyed on slug), so running them
 * from a migration makes a master push (= auto-deploy) fully provision the
 * reference data. Also ensure the public storage symlink exists so the icon
 * library's SVG URLs resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tests manage their own taxonomy data (and assert on counts), so don't
        // pre-seed there — this is only to provision real environments on deploy.
        if (app()->environment('testing')) {
            return;
        }

        foreach ([
            BusinessTypeSeeder::class,
            CommunityTypeSeeder::class,
            OfferOptionSeeder::class,
            IconSeeder::class,
        ] as $seeder) {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            // Symlink already exists (or not permitted) — safe to ignore.
        }
    }

    public function down(): void
    {
        // No-op: reference data is intentionally not removed on rollback.
    }
};
