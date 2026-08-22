<?php

declare(strict_types=1);

use Database\Seeders\KolabingVerifiedLeadsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Replace the 2026-08-18 first-pass community leads with the VERIFIED set
 * (Challenge A: identity/status/plausibility gates + blind independent recount).
 * Runs the idempotent seeder on production; re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production')) {
            (new KolabingVerifiedLeadsSeeder)->setContainer(app())->run();
        }
    }

    public function down(): void
    {
        // No-op: curated CRM data, not schema.
    }
};
