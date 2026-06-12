<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-community goals (rewards-hub). A member completes a goal by reaching
 * `target` of the chosen `earn_type` (event check-ins / a specific challenge /
 * days in the community); completion awards `reward_points` of that community's
 * points. Guarded so it is a no-op where the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_goals')) {
            return;
        }

        Schema::create('community_goals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('title');
            // event_check_ins | challenge | days_in_community
            $table->string('earn_type', 32);
            $table->unsignedInteger('target');
            $table->unsignedInteger('reward_points');
            $table->foreignUuid('challenge_id')->nullable()->constrained('challenges')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('community_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_goals');
    }
};
