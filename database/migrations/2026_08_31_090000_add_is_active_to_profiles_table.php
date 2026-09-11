<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The global active/passive switch (#254).
 *
 * Distinct from `deleted_at`, which is the *user's* irreversible choice under
 * App Review 5.1.1(v) (`DELETE /me/account`). `is_active` is an admin switch:
 * reversible, loses no data, and turns the account off everywhere at once.
 *
 * Defaults to true so every existing row stays live without a backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_test_user');

            // Read on nearly every query that touches a profile.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
