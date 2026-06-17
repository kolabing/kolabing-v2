<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 data backfill. Sets kolab_id = collab_opportunity_id for every row
 * whose collab_opportunity_id is already a real kolab id.
 *
 * This is safe for rows where collab_opportunity_id already equals a canonical
 * kolab id.
 *
 * Rows whose collab_opportunity_id is NOT in kolabs are "true legacy" (pre-bridge
 * opportunities that never had a kolab created). We do NOT touch or delete those
 * here. They are only COUNTED and logged so Phase 2 can decide how to create an
 * inverse-bridge kolab for them. Their kolab_id stays NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['applications', 'collaborations'] as $table) {
            $legacyCount = (int) DB::table($table)
                ->whereNotNull('collab_opportunity_id')
                ->whereNotIn('collab_opportunity_id', function ($query): void {
                    $query->select('id')->from('kolabs');
                })
                ->count();

            $matchedCount = (int) DB::table($table)
                ->whereNotNull('collab_opportunity_id')
                ->whereIn('collab_opportunity_id', function ($query): void {
                    $query->select('id')->from('kolabs');
                })
                ->count();

            // Backfill the matched rows: kolab_id = collab_opportunity_id.
            DB::table($table)
                ->whereNotNull('collab_opportunity_id')
                ->whereIn('collab_opportunity_id', function ($query): void {
                    $query->select('id')->from('kolabs');
                })
                ->update(['kolab_id' => DB::raw('collab_opportunity_id')]);

            $message = sprintf(
                '[kolab-sot-phase1] %s: backfilled kolab_id on %d row(s); %d true-legacy row(s) left NULL (collab_opportunity_id not in kolabs).',
                $table,
                $matchedCount,
                $legacyCount,
            );

            // Surface in migration output and the Laravel log.
            if (function_exists('logger')) {
                logger()->info($message);
            }
            echo $message.PHP_EOL;
        }
    }

    public function down(): void
    {
        // Reversible: clear the values written by this data step.
        DB::table('applications')->update(['kolab_id' => null]);
        DB::table('collaborations')->update(['kolab_id' => null]);
    }
};
