<?php

declare(strict_types=1);

use Database\Seeders\CrmSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Auto-seed the CRM on deploy (Forge runs `migrate --force`). Production only —
 * local/test DBs stay clean (they can run the seeder manually). CrmSeeder is
 * idempotent (updateOrCreate), so this is safe even if re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production')) {
            (new CrmSeeder)->run();
        }
    }

    public function down(): void
    {
        // No-op: keep seeded CRM data on rollback.
    }
};
