<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-community points: a denormalised balance per (community_id, profile_id)
 * plus an append-only ledger. The community leaderboard ranks by the balance.
 * Earning community points ALSO awards global XP via xp_earn_rules (handled in
 * the service, not here). Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_points')) {
            Schema::create('community_points', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
                $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
                $table->integer('points')->default(0);
                $table->timestamps();

                $table->unique(['community_id', 'profile_id']);
                $table->index('profile_id');
            });
        }

        if (! Schema::hasTable('community_point_ledger')) {
            Schema::create('community_point_ledger', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
                $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
                $table->integer('points');
                // event_check_in | goal_completed | challenge_verified | redemption
                $table->string('source', 40);
                $table->string('reference_id')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();

                $table->index(['community_id', 'profile_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_point_ledger');
        Schema::dropIfExists('community_points');
    }
};
