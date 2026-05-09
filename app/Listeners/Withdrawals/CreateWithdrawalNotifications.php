<?php

declare(strict_types=1);

namespace App\Listeners\Withdrawals;

use App\Events\Withdrawals\WithdrawalApproved;
use App\Events\Withdrawals\WithdrawalPaid;
use App\Events\Withdrawals\WithdrawalRejected;
use App\Models\WithdrawalRequest;
use App\Services\NotificationService;

class CreateWithdrawalNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(object $event): void
    {
        $withdrawalRequestId = match (true) {
            $event instanceof WithdrawalApproved => $event->withdrawalRequestId,
            $event instanceof WithdrawalRejected => $event->withdrawalRequestId,
            $event instanceof WithdrawalPaid => $event->withdrawalRequestId,
            default => null,
        };

        if ($withdrawalRequestId === null) {
            return;
        }

        $withdrawalRequest = WithdrawalRequest::query()->find($withdrawalRequestId);

        if ($withdrawalRequest === null) {
            return;
        }

        match (true) {
            $event instanceof WithdrawalApproved => $this->notificationService->notifyWithdrawalApproved($withdrawalRequest),
            $event instanceof WithdrawalRejected => $this->notificationService->notifyWithdrawalRejected($withdrawalRequest, $event->reason),
            $event instanceof WithdrawalPaid => $this->notificationService->notifyWithdrawalPaid($withdrawalRequest),
            default => null,
        };
    }
}
