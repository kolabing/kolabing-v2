<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // collaboration | community_main | community_custom | event
            $table->string('type', 32);
            // Collaboration threads key on the application (chat can start before a
            // collaboration row exists; preserves today's behaviour). community_*/event
            // threads use community_id (+ event_id).
            $table->foreignUuid('application_id')->nullable()->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('community_id')->nullable()->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('slug')->nullable();   // custom-chat key, matched vs tier permissions.chat_channels
            $table->string('name')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable(); // business "active" filter + sort
            $table->timestamps();

            $table->unique('application_id'); // one collaboration thread per application
            $table->index(['type', 'last_message_at']);
            $table->index('community_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};
