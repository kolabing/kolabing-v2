<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ghost invite (kolabing-v2#246).
 *
 * `encounters` (#244) can already hold a meeting whose other side is nobody —
 * `other_profile_id` is nullable and `ghost_name` exists. What it could not do
 * is turn that row into an invitation and pay it out later. These columns are
 * the difference.
 *
 * The person standing next to an attendee at an event, without the app, is the
 * most qualified prospect this product will ever have. Until now they could not
 * take part at all: `initiate` needs a `verifier_profile_id` and both parties
 * checked in, so the one moment where acquisition is easiest was the one moment
 * the product could not use.
 *
 * `pending_points` is frozen at invite time on purpose. The inviter is shown a
 * number — "Ana gets 15 XP for both of you when she joins" — and that promise
 * has to survive an admin later retuning what the challenge is worth. A promise
 * recomputed at payout is not a promise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table): void {
            // What was going to be played. Nullable because a meeting recorded
            // from a verified completion does not need it — the completion is
            // the record — and a ghost does, because there is no completion.
            $table->foreignUuid('challenge_id')->nullable()->after('event_id')
                ->constrained('challenges')->nullOnDelete();

            // The short code a human types off a landing page, and the token in
            // the invite URL. One value, two doors — see EncounterService.
            $table->string('ghost_claim_token', 16)->nullable()->unique()->after('ghost_name');

            // Optional, and only ever used to make the invite easier to send.
            // Asking a stranger for their number at the moment you meet them is
            // both bad manners and a larger data-protection surface than this
            // feature needs, so it is never required.
            $table->string('ghost_contact')->nullable()->after('ghost_claim_token');

            // Waiting on the ghost to join. Named on screen precisely because
            // it is NOT paid yet: paying up front invites imaginary friends,
            // paying nothing means nobody bothers to send the invite.
            $table->unsignedInteger('pending_points')->default(0)->after('times_met');

            // Ghosts only. Unclaimed rows expire silently at this: no
            // notification, no penalty, no shaming.
            $table->timestamp('expires_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('challenge_id');
            $table->dropColumn([
                'ghost_claim_token',
                'ghost_contact',
                'pending_points',
                'expires_at',
            ]);
        });
    }
};
