<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The opt-out behind the app's "Event reminders" toggle (kolabing-app#191).
 *
 * Defaults TRUE, unlike `marketing_tips`: reminders about something the user
 * already signed up for are transactional, so the switch has to start on or the
 * toggle the app ships would silently be off for everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('events_enabled')->default(true)->after('marketing_tips');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('events_enabled');
        });
    }
};
