<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            BusinessTypeSeeder::class,
            CommunityTypeSeeder::class,
            OfferOptionSeeder::class,
            IconSeeder::class,
            BadgeSeeder::class,
            SystemChallengeSeeder::class,
            XpEarnRuleSeeder::class,
            XpLevelSeeder::class,
            RewardEconomicsSeeder::class,
            BlogPostSeeder::class,
            RealisticDataSeeder::class,
        ]);
    }
}
