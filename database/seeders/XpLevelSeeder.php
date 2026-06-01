<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\XpLevel;
use Illuminate\Database\Seeder;

/**
 * Seed the 5-tier XP ladder. Values match the kolabing-app's xp_level.dart
 * constants so the next mobile release can swap the hardcoded ladder for the
 * /gamification/config response without behavioural drift. Idempotent.
 */
class XpLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['number' => 1, 'title' => 'New Community', 'min_xp' => 0, 'max_xp' => 99, 'color' => '#BDBDBD', 'position' => 10],
            ['number' => 2, 'title' => 'Active Community', 'min_xp' => 100, 'max_xp' => 249, 'color' => '#90CAF9', 'position' => 20],
            ['number' => 3, 'title' => 'Trusted Community', 'min_xp' => 250, 'max_xp' => 499, 'color' => '#FFD361', 'position' => 30],
            ['number' => 4, 'title' => 'Local Favorite', 'min_xp' => 500, 'max_xp' => 999, 'color' => '#FF9F43', 'position' => 40],
            ['number' => 5, 'title' => 'Local Legend', 'min_xp' => 1000, 'max_xp' => null, 'color' => '#8E50F1', 'position' => 50],
        ];

        foreach ($levels as $level) {
            XpLevel::query()->updateOrCreate(
                ['number' => $level['number']],
                $level,
            );
        }
    }
}
