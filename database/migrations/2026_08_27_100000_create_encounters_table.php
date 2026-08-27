<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ledger of the people you met (kolabing-v2#244, kolabing-app#183).
 *
 * `challenge_completions` records an **action**: that a challenge happened and
 * what it paid. Nothing anywhere records that two people **met**. So a finished
 * challenge leaves two ledger rows and no relationship, the second meeting is
 * worth exactly what the first was, and there is nothing for a between-events
 * loop — quests, a night recap, "people you have met are going on Saturday" —
 * to read from.
 *
 * This is that missing row.
 *
 * ## One row per pair per event, and each row is a historical fact
 *
 * A row says: *at this event, these two met, and it was their Nth time.*
 * `times_met` is therefore never updated — the row written at the third event
 * says 3 and keeps saying 3 forever. The current count for a pair is the
 * `times_met` of its most recent row.
 *
 * This is the shape that makes the important rule free: **a meeting is an
 * EVENT, not a challenge.** Ten challenges with the same person in one night is
 * one row and one meeting, enforced by the unique index rather than by a
 * service rule somebody has to remember. It also makes the number mean the
 * right thing — *how many times were we in the same room* — and it never drifts,
 * because nothing is recounted or denormalised after the fact.
 *
 * ## An encounter is not a friendship
 *
 * Nothing here writes to `friendships`. The encounter is a fact; a friendship
 * stays a choice the app offers and the person makes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The viewer. Rows are written one per direction, so a meeting
            // between two real profiles is two rows — which is what makes
            // "who have I met" an index scan on profile_id rather than an OR
            // across two columns.
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();

            // Null while the other side is a ghost: someone met at an event who
            // does not have the app yet. Ghost invites are a later phase of the
            // same design; the columns are here so that phase stays additive.
            $table->foreignUuid('other_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->string('ghost_name')->nullable();

            $table->foreignUuid('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();

            $table->timestamp('met_at');

            // Which meeting this was for the pair: 1, 2, 3… Written once and
            // never touched again. See the class docblock.
            $table->unsignedInteger('times_met')->default(1);

            // The frame the pair took, copied off the completion so the meeting
            // keeps its picture independently of the completion row.
            $table->string('proof_photo_url')->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'met_at'], 'encounters_by_viewer');
        });

        // Partial, because ghosts have no `other_profile_id` and one attendee
        // may hold several unclaimed ghosts at a single event. Postgres and
        // SQLite both treat NULLs as distinct anyway; saying so keeps the
        // intent readable rather than accidental.
        DB::statement(
            'CREATE UNIQUE INDEX encounters_unique_per_event
             ON encounters (profile_id, other_profile_id, event_id)
             WHERE other_profile_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
