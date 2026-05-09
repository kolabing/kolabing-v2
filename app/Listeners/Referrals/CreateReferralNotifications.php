<?php

declare(strict_types=1);

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralRewardEarned;
use App\Models\Profile;
use App\Services\NotificationService;

class CreateReferralNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(ReferralRewardEarned $event): void
    {
        $recipient = Profile::query()->find($event->recipientProfileId);

        if ($recipient === null) {
            return;
        }

        $actor = $event->actorProfileId !== null
            ? Profile::query()->find($event->actorProfileId)
            : null;

        $this->notificationService->notifyReferralRewardEarned(
            recipient: $recipient,
            code: $event->code,
            actor: $actor,
            referenceId: $event->referenceId,
        );
    }
}
