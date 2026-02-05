<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use Illuminate\Database\Seeder;

class SystemChallengeSeeder extends Seeder
{
    /**
     * Seed system challenges.
     */
    public function run(): void
    {
        $challenges = [
            [
                'name' => 'Take a selfie together',
                'description' => 'Take a fun selfie with your new friend!',
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 5,
            ],
            [
                'name' => 'Have a 2-minute conversation',
                'description' => 'Talk for at least 2 minutes about anything.',
                'difficulty' => ChallengeDifficulty::Medium,
                'points' => 15,
            ],
            [
                'name' => 'Dance on stage together',
                'description' => 'Hit the stage and show your moves!',
                'difficulty' => ChallengeDifficulty::Hard,
                'points' => 30,
            ],
            [
                'name' => 'Exchange social media handles',
                'description' => 'Follow each other on social media.',
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 5,
            ],
            [
                'name' => 'Find 3 things you have in common',
                'description' => 'Discover what you share in common.',
                'difficulty' => ChallengeDifficulty::Medium,
                'points' => 15,
            ],
        ];

        foreach ($challenges as $challengeData) {
            Challenge::query()->updateOrCreate(
                ['name' => $challengeData['name'], 'is_system' => true],
                [
                    'description' => $challengeData['description'],
                    'difficulty' => $challengeData['difficulty'],
                    'points' => $challengeData['points'],
                    'is_system' => true,
                    'event_id' => null,
                ]
            );
        }
    }
}
