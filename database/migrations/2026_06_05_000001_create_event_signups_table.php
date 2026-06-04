<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upcoming-event sign-ups (NF-CHAT Phase 3). Binary "I'm going" with capacity +
 * waitlist. One row per (event, profile); a re-join flips cancelled -> going.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_signups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('status', 20)->default('going'); // going | waitlisted | cancelled
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'profile_id']);
            $table->index(['event_id', 'status']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_signups');
    }
};
