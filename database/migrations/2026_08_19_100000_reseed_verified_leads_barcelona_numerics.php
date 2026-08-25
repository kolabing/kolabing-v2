<?php

declare(strict_types=1);

use Database\Seeders\KolabingVerifiedLeadsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-run the verified-leads seeder now that it (a) includes Barcelona (8th city,
 * 165 total) and (b) writes numeric audience_count + locality flags for scoring.
 * Idempotent (updateOrCreate); refreshes the existing rows and adds Barcelona.
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
        // No-op: curated CRM data.
    }
};
