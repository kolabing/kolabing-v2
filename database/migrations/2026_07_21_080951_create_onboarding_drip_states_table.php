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
        Schema::create('onboarding_drip_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->timestamp('anchor_at');
            $table->unsignedTinyInteger('next_sequence')->default(0);
            $table->unsignedTinyInteger('last_sent_sequence')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('cascade');

            $table->unique('profile_id', 'onboarding_drip_states_profile_unique');
            $table->index(['scheduled_for', 'cancelled_at'], 'onboarding_drip_states_schedule_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_drip_states');
    }
};
