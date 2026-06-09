<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-member chat ban (overrides tier access). Chats are tier-gated, but a
 * manager can block an individual from a specific thread regardless of their
 * tier; the ban is checked in ChatService::canAccessThread and excludes the
 * thread from their inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_thread_bans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'profile_id']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_thread_bans');
    }
};
