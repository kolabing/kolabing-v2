<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the one Kolab the platform lists for a business on its behalf
     * (BusinessAutoListingService). The flag is what makes provisioning
     * idempotent and what stops a listing the business closed from ever being
     * recreated; it grants and gates nothing.
     */
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->boolean('is_auto_listing')->default(false)->after('status');
            $table->index(['creator_profile_id', 'is_auto_listing']);
        });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropIndex(['creator_profile_id', 'is_auto_listing']);
            $table->dropColumn('is_auto_listing');
        });
    }
};
