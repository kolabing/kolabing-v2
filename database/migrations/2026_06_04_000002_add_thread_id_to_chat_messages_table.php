<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            // Nullable during the transition; application_id is retained so existing
            // queries keep working while chat migrates onto threads (lazy backfill).
            $table->foreignUuid('thread_id')->nullable()->after('application_id')
                ->constrained('chat_threads')->cascadeOnDelete();
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropForeign(['thread_id']);
            $table->dropIndex(['thread_id', 'created_at']);
            $table->dropColumn('thread_id');
        });
    }
};
