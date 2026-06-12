<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member redeeming a community reward: deducts points + decrements stock and
 * records the redemption. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reward_redemptions')) {
            return;
        }

        Schema::create('reward_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('reward_id')->constrained('community_rewards')->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            // pending | fulfilled | cancelled
            $table->string('status', 20)->default('pending');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['community_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
