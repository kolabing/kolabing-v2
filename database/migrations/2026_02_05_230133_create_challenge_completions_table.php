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
        Schema::create('challenge_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('challenger_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('verifier_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('status', 10)->default('pending');
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['challenge_id', 'event_id', 'challenger_profile_id', 'verifier_profile_id'],
                'challenge_completions_unique'
            );
            $table->index('challenger_profile_id');
            $table->index('verifier_profile_id');
            $table->index('event_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_completions');
    }
};
