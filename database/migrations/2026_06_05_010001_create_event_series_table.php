<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring-event series (NF-16 recurring, Phase 1).
 *
 * A series is the rule a community defines ("every Sunday", "Tue + Thu",
 * "monthly on the 1st Saturday"). Concrete occurrences are ordinary rows in
 * `events` (each with its own sign-ups / chat / tier-gate), linked back via
 * `events.series_id`. We materialise a rolling 3-month window and extend it on
 * demand, so there is no dead-end and no infinite generator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_series', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();

            $table->string('name', 100);

            // Recurrence rule.
            $table->string('frequency', 10);            // weekly | biweekly | monthly
            $table->json('byweekday');                  // [0..6], 0=Sun .. 6=Sat (multi-day: e.g. [2,4] = Tue+Thu)
            $table->string('time_of_day', 5);           // "HH:MM" local start time
            $table->unsignedInteger('duration_minutes')->nullable(); // null = no ends_at

            // Shared occurrence template.
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('tier_gate')->nullable();
            $table->string('chat_mode', 10)->default('per_event'); // per_event | series

            // End rule + rolling horizon.
            $table->string('ends_mode', 10)->default('never');     // count | until | never
            $table->unsignedInteger('ends_count')->nullable();     // total occurrences when ends_mode=count
            $table->date('ends_on')->nullable();                   // last date when ends_mode=until
            $table->date('starts_on');                             // first occurrence date
            $table->date('materialized_through')->nullable();      // furthest date generated so far

            $table->timestamps();

            $table->index('community_id');
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_series');
    }
};
