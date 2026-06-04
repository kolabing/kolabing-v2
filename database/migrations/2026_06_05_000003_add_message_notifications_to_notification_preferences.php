<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user toggle for chat-message notifications (push + in-app feed).
 * Default ON — members are notified of new community/event/collaboration
 * messages unless they opt out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('message_notifications')->default(true)->after('collaboration_updates');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('message_notifications');
        });
    }
};
