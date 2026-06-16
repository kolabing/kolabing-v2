<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event visibility (public | members | tier).
 *
 * - `public`  — anyone may SEE the event (it surfaces in city discover); signup
 *   rules unchanged.
 * - `members` — the existing default behaviour (members of the host community).
 * - `tier`    — reuses the existing `tier_gate` (allowed tier ids).
 *
 * Existing rows default to `members` so nothing silently becomes public. Stored
 * as a portable string + CHECK rather than a native enum so the same migration
 * runs on Postgres (prod) and SQLite (tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'visibility')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->string('visibility')->default('members')->after('tier_gate');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('events', 'visibility')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
