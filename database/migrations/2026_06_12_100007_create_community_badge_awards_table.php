<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which member earned which community badge (auto-awarded on criteria).
 * Unique per (community_badge_id, profile_id) so award is idempotent. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_badge_awards')) {
            return;
        }

        Schema::create('community_badge_awards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_badge_id')->constrained('community_badges')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['community_badge_id', 'profile_id']);
            $table->index(['community_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_badge_awards');
    }
};
