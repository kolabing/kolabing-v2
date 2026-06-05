<?php

use App\Enums\EventVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PUBLIC EVENTS lane (Batch 3). A `public` event is discoverable + RSVP-able by
 * any attendee; a `members_only` event requires active membership of the owning
 * community first. Defaults to `members_only` so existing events stay gated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('visibility')->default(EventVisibility::MembersOnly->value)->after('tier_gate');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
