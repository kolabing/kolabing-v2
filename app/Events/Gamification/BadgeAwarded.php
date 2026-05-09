<?php

declare(strict_types=1);

namespace App\Events\Gamification;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class BadgeAwarded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $profileId,
        public string $badgeName,
        public string $targetId,
        public string $targetType,
        public string $dedupeKey,
    ) {}
}
