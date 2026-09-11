<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a member last actually turned up (kolabing-app#147).
 *
 * An **Active Member** is a Member who attended within the last 90 days, and
 * `community_members` had no notion of attendance at all — only `joined_at`,
 * which says when they signed up and nothing about whether they ever came back.
 *
 * Denormalised deliberately. "How many active members" is a count over a whole
 * community, and deriving it from `event_checkins` joined through
 * `events.community_id` means that join on every read of every community in a
 * list. One timestamp written on the way in is cheaper than the same answer
 * recomputed on every way out — and this codebase has already paid for the other
 * choice once, when per-row counts on CommunityResource took
 * /me/rewards-overview from 12 queries to 21.
 *
 * Backfilled from history, so nobody who has been attending for a year reads as
 * inactive the day this ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_members', function (Blueprint $table): void {
            $table->timestamp('last_attended_at')->nullable()->after('joined_at');
            // The only query this column exists for: active members of one
            // community.
            $table->index(['community_id', 'last_attended_at']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('community_members', function (Blueprint $table): void {
            $table->dropIndex(['community_id', 'last_attended_at']);
            $table->dropColumn('last_attended_at');
        });
    }

    /**
     * Newest check-in per (community, member), from events that belong to the
     * community the membership is in.
     *
     * Chunked and per-row rather than one correlated UPDATE: the same statement
     * has to run on PostgreSQL in production and SQLite under `artisan test`,
     * and update-with-join syntax is where those two diverge. This is slower and
     * it runs once.
     */
    private function backfill(): void
    {
        DB::table('community_members')
            ->select('id', 'community_id', 'profile_id')
            ->orderBy('id')
            ->chunk(500, function ($members): void {
                foreach ($members as $member) {
                    $last = DB::table('event_checkins')
                        ->join('events', 'events.id', '=', 'event_checkins.event_id')
                        ->where('events.community_id', $member->community_id)
                        ->where('event_checkins.profile_id', $member->profile_id)
                        ->max('event_checkins.checked_in_at');

                    if ($last !== null) {
                        DB::table('community_members')
                            ->where('id', $member->id)
                            ->update(['last_attended_at' => $last]);
                    }
                }
            });
    }
};
