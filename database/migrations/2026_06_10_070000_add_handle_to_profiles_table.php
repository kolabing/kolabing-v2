<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universal `@handle` for every profile (attendees, leaders, businesses). Stored
 * lowercase, unique, nullable (legacy rows have none until they next save).
 * Format `^[a-z0-9_]{3,20}$` is enforced at the application layer on every write.
 * Guarded so it is a no-op where the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'handle')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->string('handle')->nullable()->unique()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'handle')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropUnique(['handle']);
                $table->dropColumn('handle');
            });
        }
    }
};
