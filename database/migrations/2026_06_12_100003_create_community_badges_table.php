<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-community badges, auto-awarded when a member meets the criteria.
 * criteria_type: points_threshold | event_check_ins | days_in_community |
 * challenges_completed. `challenge_ids` lists the qualifying challenges when
 * criteria_type = challenges_completed. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_badges')) {
            return;
        }

        Schema::create('community_badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('title');
            $table->string('icon')->nullable();
            // points_threshold | event_check_ins | days_in_community | challenges_completed
            $table->string('criteria_type', 32);
            $table->unsignedInteger('criteria_value');
            $table->json('challenge_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('community_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_badges');
    }
};
