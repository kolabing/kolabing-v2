<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ICS `SEQUENCE` for calendar invitations (#252).
 *
 * Bumped only when the event's time or place changes, and sent with the same
 * per-event `UID`. That pair is what makes a calendar update the entry someone
 * already has instead of adding a second one — and why a rename must NOT bump
 * it: churn in someone's calendar is its own kind of spam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('ics_sequence')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('ics_sequence');
        });
    }
};
