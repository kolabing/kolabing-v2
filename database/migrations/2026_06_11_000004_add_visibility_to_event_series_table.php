<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event-series visibility (public | members | tier) — the series-level mirror of
 * the per-event `events.visibility` column.
 *
 * - `public`  — occurrences materialised from this series surface in city
 *   discover; signup rules unchanged.
 * - `members` — the historical default (members of the host community).
 * - `tier`    — reuses the series `tier_gate` (allowed tier ids), propagated to
 *   each occurrence.
 *
 * Existing rows default to `members` so nothing silently becomes public. Stored
 * as a portable string + index (same as the events migration) so it runs on
 * Postgres (prod) and SQLite (tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_series', 'visibility')) {
            return;
        }

        Schema::table('event_series', function (Blueprint $table): void {
            $table->string('visibility')->default('members')->after('tier_gate');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_series', 'visibility')) {
            return;
        }

        Schema::table('event_series', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
