<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_thread_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            // joined | banned
            $table->string('state', 16);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->foreignUuid('banned_by')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_thread_participants');
    }
};
