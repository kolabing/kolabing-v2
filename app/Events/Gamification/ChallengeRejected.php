<?php

declare(strict_types=1);

namespace App\Events\Gamification;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class ChallengeRejected implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $challengeCompletionId,
    ) {}
}
