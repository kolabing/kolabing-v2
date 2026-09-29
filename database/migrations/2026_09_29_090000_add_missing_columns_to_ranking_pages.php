<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-FX-79. `host_venues` and `spotlight_top` were added to
 * 2026_08_18_130000_create_community_rankings AFTER that migration had already
 * run on production (commit 4ed336b), so production never got them and
 * RankingPageSeeder failed on the first insert. A fresh database already has
 * both columns from the create migration, so each one is added only when it is
 * missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $addHostVenues = ! Schema::hasColumn('ranking_pages', 'host_venues');
        $addSpotlightTop = ! Schema::hasColumn('ranking_pages', 'spotlight_top');

        if (! $addHostVenues && ! $addSpotlightTop) {
            return;
        }

        Schema::table('ranking_pages', function (Blueprint $table) use ($addHostVenues, $addSpotlightTop): void {
            if ($addHostVenues) {
                $table->json('host_venues')->nullable()->after('verticals');
            }

            if ($addSpotlightTop) {
                $table->boolean('spotlight_top')->default(false)->after('published');
            }
        });
    }

    /**
     * Nothing to undo: on a fresh database these columns belong to the create
     * migration, and dropping them here would break its down()/up() cycle.
     */
    public function down(): void {}
};
