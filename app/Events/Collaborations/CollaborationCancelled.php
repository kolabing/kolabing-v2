<?php

declare(strict_types=1);

namespace App\Events\Collaborations;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class CollaborationCancelled implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $collaborationId,
        public ?string $actorProfileId = null,
        public ?string $reason = null,
    ) {}
}
