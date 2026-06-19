<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Historically backfilled canonical kolabs from legacy collab_opportunities via
 * InverseLegacyOpportunityBridgeService. That service has been removed; the legacy
 * table is archived and already fully mirrored into kolabs. This migration is now
 * inert (it already ran in every live environment, and a fresh database has no
 * legacy rows to backfill). Kept as a no-op to preserve the migration ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally a no-op. See class docblock.
    }

    public function down(): void
    {
        // Intentionally a no-op.
    }
};
