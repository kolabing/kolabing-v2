<?php

declare(strict_types=1);

namespace App\Listeners\Applications;

use App\Events\Applications\ApplicationAccepted;
use App\Events\Applications\ApplicationCreated;
use App\Events\Applications\ApplicationDeclined;
use App\Models\Application;
use App\Services\NotificationService;

class CreateApplicationNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(object $event): void
    {
        $applicationId = match (true) {
            $event instanceof ApplicationCreated => $event->applicationId,
            $event instanceof ApplicationAccepted => $event->applicationId,
            $event instanceof ApplicationDeclined => $event->applicationId,
            default => null,
        };

        if ($applicationId === null) {
            return;
        }

        $application = Application::query()->find($applicationId);

        if ($application === null) {
            return;
        }

        match (true) {
            $event instanceof ApplicationCreated => $this->notificationService->notifyApplicationReceived($application),
            $event instanceof ApplicationAccepted => $this->notificationService->notifyApplicationAccepted($application),
            $event instanceof ApplicationDeclined => $this->notificationService->notifyApplicationDeclined($application),
            default => null,
        };
    }
}
