<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-tracked mission progress ledger. One row per (challenge, earner, period
 * bucket). `challenge_completions` stays the peer-verified event ledger and is
 * NOT used for missions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->unsignedInteger('progress_count')->default(0);
            $table->unsignedInteger('target_value');
            $table->timestamp('completed_at')->nullable();
            $table->string('period_key', 20)->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'profile_id', 'period_key'], 'challenge_progress_unique_idx');
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_progress');
    }
};
