<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EVENT's own city (where it actually happens), picked at creation.
 *
 * Discover derives an event's "effective city" as events.city_id when set, else
 * the host community's community_profiles.city_id. This lets a leader-created
 * community (type=other, community_profile_id=null → no profile city) still pin
 * its events to a city and surface in /events/discover. Nullable so legacy rows
 * and community-derived events keep working. Indexed for the city filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'city_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignUuid('city_id')->nullable()->after('community_id')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('events', 'city_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
