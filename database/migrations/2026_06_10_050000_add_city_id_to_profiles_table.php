<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendee identity (name, avatar, city) lives on the base `profiles` table —
 * attendees have no extended profile carrying these fields. Business and
 * community users keep their city on their extended profile, but attendees need
 * a city on the base record so `PUT /me/profile` can persist it. The `cities`
 * primary key is a `uuid` named `id` (see create_cities_table), so the FK is a
 * nullable foreignUuid. Guarded so it is a no-op where the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'city_id')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->foreignUuid('city_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('cities')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'city_id')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('city_id');
            });
        }
    }
};
