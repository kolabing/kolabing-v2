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
            // The peer-playable library communities curate from (#150). Kept
            // separate from SystemChallengeSeeder because that one seeds
            // trigger-driven missions, and mixing the two would blur the line
            // that decides which surface a challenge appears on.
            PeerChallengeLibrarySeeder::class,
            XpEarnRuleSeeder::class,
            XpLevelSeeder::class,
            RewardEconomicsSeeder::class,
            BlogPostSeeder::class,
            RealisticDataSeeder::class,
            // Public community-rankings directory. Idempotent; only Wave-1 pages publish
            // (the new category pages ship published=false pending Maria's review). Runs
            // after the community leads exist (KolabingCommunityLeadsSeeder, PR #157);
            // Barcelona leads are created here regardless.
            RankingPageSeeder::class,
        ]);
    }
}
