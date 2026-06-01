<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table that controls the community reward economy:
     * the referral milestone + cash, the per-point cents rate, and the
     * withdrawal threshold. Read by RewardEconomicsService; surfaced at
     * /admin/gamification/economics. Replaces the hardcoded `375` / `0.20`
     * literals in GamificationController::withdrawal().
     */
    public function up(): void
    {
        Schema::create('reward_economics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('referral_goal')->default(3);
            $table->unsignedInteger('referral_cash_reward_cents')->default(7500);
            $table->unsignedSmallInteger('euro_cents_per_point')->default(20);
            $table->unsignedInteger('withdrawal_threshold_cents')->default(7500);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_economics');
    }
};
