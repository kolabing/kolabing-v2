<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            // Soft-delete so deleted custom/event chats are recoverable.
            $table->softDeletes();
            // When true (custom chats only), active community members may self-join.
            $table->boolean('is_open')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('is_open');
        });
    }
};
