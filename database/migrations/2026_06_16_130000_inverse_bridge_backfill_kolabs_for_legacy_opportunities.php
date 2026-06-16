<?php

declare(strict_types=1);

use App\Services\InverseLegacyOpportunityBridgeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 inverse-bridge backfill.
 *
 * Phase 1 left "true legacy" rows with kolab_id = NULL: applications /
 * collaborations whose collab_opportunity_id points at a collab_opportunities
 * row that has NO backing kolab (pre-bridge rows). Before we re-point reads to
 * kolabs, we must give every such opportunity a kolab.
 *
 * This migration calls InverseLegacyOpportunityBridgeService::backfillAll(),
 * which:
 *   1. Creates a kolab (with id = opportunity.id) for every collab_opportunity
 *      that has no matching kolab — the faithful inverse of
 *      LegacyOpportunityBridgeService::fillFromKolab().
 *   2. Sets applications.kolab_id / collaborations.kolab_id = collab_opportunity_id
 *      for the now-backfilled rows.
 *
 * Goal: ZERO applications/collaborations left with kolab_id = NULL.
 *
 * Non-destructive: collab_opportunities and collab_opportunity_id are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $counts = app(InverseLegacyOpportunityBridgeService::class)->backfillAll();

        $message = sprintf(
            '[kolab-sot-phase2] inverse-bridge backfill: opportunities_without_kolab %d -> %d (created %d kolabs); '
            .'applications NULL kolab_id %d -> %d; collaborations NULL kolab_id %d -> %d.',
            $counts['opportunities_without_kolab_before'],
            $counts['opportunities_without_kolab_after'],
            $counts['kolabs_created'],
            $counts['applications_null_kolab_before'],
            $counts['applications_null_kolab_after'],
            $counts['collaborations_null_kolab_before'],
            $counts['collaborations_null_kolab_after'],
        );

        if (function_exists('logger')) {
            logger()->info($message);
        }
        echo $message.PHP_EOL;
    }

    public function down(): void
    {
        // Best-effort reverse: clear kolab_id values this migration set is not
        // distinguishable from Phase 1's, so we only clear the FK pointers and
        // delete kolabs that were materialized as exact mirrors of a legacy
        // opportunity (id present in BOTH tables but with no other kolab origin).
        // We cannot perfectly tell apart inverse-bridged kolabs from genuine
        // kolabs that happen to also have a compatibility opportunity, so this
        // down() is intentionally conservative: it leaves kolabs in place and
        // only nulls the FKs for rows whose collab_opportunity still exists.
        foreach (['applications', 'collaborations'] as $table) {
            DB::table($table)
                ->whereNotNull('kolab_id')
                ->whereColumn('kolab_id', 'collab_opportunity_id')
                ->update(['kolab_id' => null]);
        }
    }
};
