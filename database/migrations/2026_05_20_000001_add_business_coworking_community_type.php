<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('community_types')
            ->where('slug', 'business-coworking')
            ->exists();

        if ($exists) {
            return;
        }

        $hobbyOrder = (int) (DB::table('community_types')
            ->where('slug', 'hobby-community')
            ->value('sort_order') ?? 0);

        DB::table('community_types')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Business / Coworking',
            'slug' => 'business-coworking',
            'icon' => 'briefcase',
            'sort_order' => $hobbyOrder,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('community_types')
            ->where('slug', 'business-coworking')
            ->delete();
    }
};
