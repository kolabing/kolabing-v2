<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The series' own city. Each materialised occurrence inherits this as its
 * events.city_id (see EventSeriesService::materialize), so a recurring public
 * series surfaces in city discover the same way a one-off does. Nullable +
 * indexed, mirrors events.city_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_series', 'city_id')) {
            return;
        }

        Schema::table('event_series', function (Blueprint $table): void {
            $table->foreignUuid('city_id')->nullable()->after('community_id')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_series', 'city_id')) {
            return;
        }

        Schema::table('event_series', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
