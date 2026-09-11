<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table controlling how many extra discovery-match
     * points a Trusted Partner / Community Favourite business gets. Read by
     * BusinessVisibilityBoostService; surfaced at
     * /admin/gamification/business-visibility-boost. Falls back to
     * config('gamification_business.visibility_boost_points') defaults when
     * no row exists.
     */
    public function up(): void
    {
        Schema::create('business_visibility_boost_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('trusted_partner_points')->default(5);
            $table->unsignedSmallInteger('community_favourite_points')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_visibility_boost_settings');
    }
};
