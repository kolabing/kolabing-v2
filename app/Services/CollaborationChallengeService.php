<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use App\Models\Collaboration;

class CollaborationChallengeService
{
    /**
     * Sync selected challenges for a collaboration.
     *
     * @param  array<int, string>  $challengeIds
     * @return array<int, string>
     */
    public function syncChallenges(Collaboration $collaboration, array $challengeIds): array
    {
        $collaboration->challenges()->sync($challengeIds);

        return $collaboration->challenges()->pluck('challenges.id')->toArray();
    }

    /**
     * Create a custom challenge for a collaboration.
     *
     * @param  array{name: string, description?: string, difficulty: string, points?: int}  $data
     */
    public function createCustomChallenge(Collaboration $collaboration, array $data): Challenge
    {
        $difficulty = ChallengeDifficulty::from($data['difficulty']);

        $challenge = Challenge::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'difficulty' => $difficulty,
            'points' => $data['points'] ?? $difficulty->points(),
            'is_system' => false,
            'event_id' => $collaboration->event_id,
        ]);

        // Auto-select the newly created challenge for this collaboration
        $collaboration->challenges()->attach($challenge->id);

        return $challenge;
    }
}
