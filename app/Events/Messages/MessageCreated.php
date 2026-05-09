<?php

declare(strict_types=1);

namespace App\Events\Messages;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class MessageCreated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $messageId,
        public string $applicationId,
    ) {}
}
