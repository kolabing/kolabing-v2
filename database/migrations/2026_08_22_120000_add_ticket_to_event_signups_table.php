<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets. A sign-up becomes something an attendee can be let in with.
 *
 * Until now `event_signups` recorded an intention ("going") and the door worked the
 * other way round: the host displayed one QR for the whole event and attendees
 * scanned it (`events.checkin_token`). That still works and is untouched. This adds
 * the direction people expect from a ticket — the attendee carries proof, the host
 * scans it — which is also what makes an emailable, keepable artefact possible.
 *
 * One identifier, not two. `ticket_code` is what the QR encodes *and* what a
 * doorkeeper types when the QR will not scan (cracked screen, dead battery, bright
 * sun); both are the same permission, so splitting them would only invite the two
 * paths to drift. Ten characters from an unambiguous alphabet keeps the QR at
 * version 3 (29×29 modules — scannable across a room rather than at arm's length)
 * while leaving ~10^15 codes, and the code is not the security boundary anyway:
 * admitting requires an authenticated host of that event.
 *
 * Nullable throughout: a waitlisted sign-up holds no seat, so it has no ticket, and
 * neither does any row that existed before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_signups', function (Blueprint $table): void {
            $table->string('ticket_code', 16)->nullable()->after('waitlist_position');
            $table->timestamp('ticket_issued_at')->nullable()->after('ticket_code');
            /*
             * Separate from issued_at so a bounced or queued send can be retried
             * without minting a new ticket — the code already on the attendee's
             * screen must not change because an email failed.
             */
            $table->timestamp('ticket_emailed_at')->nullable()->after('ticket_issued_at');

            $table->unique('ticket_code');
        });
    }

    public function down(): void
    {
        Schema::table('event_signups', function (Blueprint $table): void {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn(['ticket_code', 'ticket_issued_at', 'ticket_emailed_at']);
        });
    }
};
