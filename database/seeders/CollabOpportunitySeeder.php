<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class CollabOpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessProfiles = Profile::where('user_type', 'business')->get();

        if ($businessProfiles->isEmpty()) {
            $this->command->warn('No business profiles found. Skipping opportunity seeding.');

            return;
        }

        foreach ($businessProfiles as $profile) {
            // 3 published opportunities per business
            CollabOpportunity::factory()
                ->count(3)
                ->published()
                ->forCreator($profile)
                ->create();

            // 1 draft opportunity per business
            CollabOpportunity::factory()
                ->forCreator($profile)
                ->create();

            // 1 closed opportunity per business
            CollabOpportunity::factory()
                ->closed()
                ->forCreator($profile)
                ->create();
        }

        $this->command->info('Seeded '.($businessProfiles->count() * 5).' opportunities for '.$businessProfiles->count().' business profiles.');
    }
}
