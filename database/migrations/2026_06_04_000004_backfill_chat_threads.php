<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lazy-backfill seed: create one `collaboration` thread per existing application
 * conversation and repoint its messages, so the new threads model is correct for
 * pre-existing chats from day one. No-op on a fresh/empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conversations = DB::table('chat_messages')
            ->select('application_id')
            ->selectRaw('MIN(created_at) as first_at, MAX(created_at) as last_at')
            ->whereNull('thread_id')
            ->groupBy('application_id')
            ->get();

        foreach ($conversations as $row) {
            // Skip if a thread already exists for this application.
            $existing = DB::table('chat_threads')->where('application_id', $row->application_id)->value('id');
            $threadId = $existing ?? (string) Str::uuid();

            if ($existing === null) {
                DB::table('chat_threads')->insert([
                    'id' => $threadId,
                    'type' => 'collaboration',
                    'application_id' => $row->application_id,
                    'last_message_at' => $row->last_at,
                    'created_at' => $row->first_at,
                    'updated_at' => now(),
                ]);
            }

            DB::table('chat_messages')
                ->where('application_id', $row->application_id)
                ->whereNull('thread_id')
                ->update(['thread_id' => $threadId]);
        }
    }

    public function down(): void
    {
        // Threads are dropped by the create_chat_threads_table rollback; nothing to undo here.
    }
};
