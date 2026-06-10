<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The base `profiles` table carries a `name` column in production (the display
 * name used for attendee profiles, which have no name column of their own), but
 * the column was never added through this migration tree. This guarded migration
 * reconciles that drift so every environment (including the test database) has
 * the column the PublicProfileResource display_name fallback relies on. It is a
 * no-op where the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'name')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->string('name')->nullable()->after('user_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'name')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }
};
