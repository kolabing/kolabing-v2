<?php

declare(strict_types=1);

use Database\Seeders\CrmSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-run the (now community-aware) CrmSeeder on production. The earlier
 * 030005 seed ran before communities were added, so this top-up seeds the
 * communities + score weights. Idempotent (updateOrCreate); production only.
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
        // No-op.
    }
};
