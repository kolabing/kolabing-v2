<?php

declare(strict_types=1);

namespace App\Events\Applications;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class ApplicationAccepted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $applicationId,
    ) {}
}
