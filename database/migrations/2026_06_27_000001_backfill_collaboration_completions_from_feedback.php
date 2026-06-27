<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backward-compat backfill (PR 1 follow-up, 2026-06-27). Every
     * collaboration_feedback row created before this date predates the
     * completion-confirmation gate — without this backfill, a participant
     * who already submitted feedback under the old contract would have to
     * confirm completion again from scratch.
     *
     * Treats each existing feedback row as an implicit 'yes' completion
     * confirmation. Idempotent via `insertOrIgnore` against the existing
     * `(collaboration_id, profile_id)` unique constraint, so re-running this
     * migration (or letting the runtime fallback in
     * CollaborationCompletionService::enforceGate() create the same row
     * first) is a no-op. Awards NO XP — the old /feedback flow already paid
     * CollaborationComplete XP for these submissions; awarding
     * CollaborationCompletionConfirmed on top would double-pay.
     *
     * This is a one-time data migration, not a permanent dual-write — the
     * runtime fallback (same service method) covers any feedback row
     * submitted after this migration runs but before a client upgrades to
     * call /completion directly.
     */
    public function up(): void
    {
        $now = now();

        DB::table('collaboration_feedback')
            ->select(['collaboration_id', 'reviewer_profile_id', 'reviewer_role'])
            ->get()
            ->each(function (object $row) use ($now): void {
                DB::table('collaboration_completions')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'collaboration_id' => $row->collaboration_id,
                    'profile_id' => $row->reviewer_profile_id,
                    'role' => $row->reviewer_role,
                    'status' => 'yes',
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // No-op. This migration backfills implicit-yes confirmations from
        // pre-existing feedback rows; reverting would strand those
        // collaborations again with no way to tell backfilled rows apart
        // from genuinely user-submitted ones.
    }
};
