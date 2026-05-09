<?php

declare(strict_types=1);

namespace App\Events\Referrals;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class ReferralRewardEarned implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $recipientProfileId,
        public string $code,
        public ?string $actorProfileId = null,
        public ?string $referenceId = null,
    ) {}
}
