<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 (additive foundation) of the "kolabs is the single source of truth"
 * migration. See docs/notes/2026-06-16-kolab-sot-phase1.md and the plan at
 * kolabing-app/docs/plans/2026-06-16-kolab-source-of-truth-migration.md.
 *
 * Adds a nullable kolab_id FK -> kolabs.id to applications and collaborations.
 * The existing collab_opportunity_id columns are kept untouched (backward
 * compatible, no reads re-pointed here). Backfill happens in the following
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignUuid('kolab_id')
                ->nullable()
                ->after('collab_opportunity_id')
                ->constrained('kolabs')
                ->nullOnDelete();

            $table->index('kolab_id');
        });

        Schema::table('collaborations', function (Blueprint $table): void {
            $table->foreignUuid('kolab_id')
                ->nullable()
                ->after('collab_opportunity_id')
                ->constrained('kolabs')
                ->nullOnDelete();

            $table->index('kolab_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kolab_id');
        });

        Schema::table('collaborations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kolab_id');
        });
    }
};
