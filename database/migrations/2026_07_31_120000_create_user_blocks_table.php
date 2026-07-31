<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (blocker, blocked) pair. When a profile blocks another, that
     * blocked profile's content is removed from the blocker's feed (and vice
     * versa where cheap). Unique pair so a repeat block is idempotent.
     * Part of UGC moderation (App Review Guideline 1.2).
     */
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('blocker_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('blocked_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_profile_id', 'blocked_profile_id']);
            $table->index('blocked_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
