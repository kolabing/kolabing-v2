<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds icon_url to the type tables for admin-uploaded SVG icons. `icon` (short
 * slug/name → app-bundled SVG) is kept; `icon_url` holds an uploaded SVG's URL
 * for NEW types with no bundled asset. The app renders the bundled icon when
 * present, else falls back to icon_url (network SVG). See
 * docs/plans/2026-06-10-type-source-of-truth-DECISION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['community_types', 'business_types'] as $table) {
            if (Schema::hasColumn($table, 'icon_url')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->string('icon_url')->nullable()->after('icon');
            });
        }
    }

    public function down(): void
    {
        foreach (['community_types', 'business_types'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropColumn('icon_url');
            });
        }
    }
};
