<?php

declare(strict_types=1);

namespace App\Events\Withdrawals;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class WithdrawalRejected implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $withdrawalRequestId,
        public ?string $reason = null,
    ) {}
}
