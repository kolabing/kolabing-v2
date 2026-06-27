<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Challenge;
use App\Models\ChallengeProgress;

/**
 * Pairs a mission (Challenge row) with the viewer's progress for the CURRENT
 * period, without mutating the Eloquent model via dynamic properties.
 * Consumed by MissionResource for GET /me/missions.
 */
final readonly class MissionWithProgress
{
    public function __construct(
        public Challenge $mission,
        public ?ChallengeProgress $progress,
        public string $periodKey,
    ) {}
}
