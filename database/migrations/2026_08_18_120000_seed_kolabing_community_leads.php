<?php

declare(strict_types=1);

use Database\Seeders\KolabingCommunityLeadsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Load Neil's 2026-08-18 supply-side community-lead research (140 communities
 * across 7 EU cities + their collab businesses) into the admin CRM. Runs the
 * idempotent KolabingCommunityLeadsSeeder on production; safe to re-run
 * (updateOrCreate keyed on [type, name]).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production')) {
            (new KolabingCommunityLeadsSeeder)->setContainer(app())->run();
        }
    }

    public function down(): void
    {
        // No-op: leads are curated CRM data, not schema.
    }
};
