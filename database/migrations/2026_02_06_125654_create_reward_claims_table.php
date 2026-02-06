<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reward_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_reward_id')->constrained('event_rewards')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('challenge_completion_id')->nullable()->constrained('challenge_completions')->nullOnDelete();
            $table->string('status', 15)->default('available');
            $table->timestamp('won_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redeem_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('profile_id');
            $table->index('event_reward_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_claims');
    }
};
