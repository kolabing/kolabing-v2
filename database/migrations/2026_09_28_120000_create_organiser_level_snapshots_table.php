<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organiser levels (incentives v1, C). One row per nightly evaluation of a
 * community profile, so level-ups and level-downs keep their history. The
 * latest row per profile is the current level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organiser_level_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('level', 16);
            $table->string('previous_level', 16)->nullable();
            // Criteria values, targets and met flags as evaluated.
            $table->json('criteria');
            $table->timestamp('evaluated_at');
            // Venue-side ranking weight: level boost + league points (this month
            // plus last month decayed) + last season's division-winner boost.
            $table->unsignedInteger('discovery_score')->default(0);
            // Top-level perk: personal intros from our pipeline. Set by the team
            // on the admin user page; carried forward while the level stays Top.
            $table->timestamp('intro_done_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'evaluated_at']);
            $table->index(['level', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organiser_level_snapshots');
    }
};
