<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every community has exactly one community_main chat. Seed one for any existing
 * community that lacks it. New communities get theirs in CommunityService::create.
 * No-op on a fresh/empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $communities = DB::table('communities')->select('id', 'name', 'created_at')->get();

        foreach ($communities as $community) {
            $exists = DB::table('chat_threads')
                ->where('type', 'community_main')
                ->where('community_id', $community->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('chat_threads')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'community_main',
                'community_id' => $community->id,
                'name' => $community->name,
                'created_at' => $community->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Dropped with the chat_threads table rollback; nothing to undo here.
    }
};
