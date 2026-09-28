<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * City league (incentives v1, A). A season is one calendar month
 * (Europe/Madrid) in one city. The live table is computed on request; at
 * season end the final standings are frozen here by app:close-league-seasons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_seasons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('divisions_enabled')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'month']);
            $table->index('month');
        });

        Schema::create('league_standings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('league_seasons')->cascadeOnDelete();
            // The organiser (community profile) — the league ranks organisers.
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('division', 16);
            $table->unsignedInteger('rank');
            $table->unsignedInteger('points');
            $table->json('score_breakdown');
            $table->string('movement', 16)->nullable(); // promoted | relegated
            $table->string('badge', 16)->nullable();    // champion | top3
            // Division winners are owed personal intros; set on the admin user page.
            $table->timestamp('intro_done_at')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'profile_id']);
            $table->index(['profile_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_standings');
        Schema::dropIfExists('league_seasons');
    }
};
