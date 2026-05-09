<?php

declare(strict_types=1);

namespace App\Listeners\Collaborations;

use App\Events\Collaborations\CollaborationCancelled;
use App\Events\Collaborations\CollaborationRescheduled;
use App\Events\Collaborations\CollaborationScheduled;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\NotificationService;

class CreateCollaborationNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(object $event): void
    {
        $collaborationId = match (true) {
            $event instanceof CollaborationScheduled => $event->collaborationId,
            $event instanceof CollaborationRescheduled => $event->collaborationId,
            $event instanceof CollaborationCancelled => $event->collaborationId,
            default => null,
        };

        if ($collaborationId === null) {
            return;
        }

        $collaboration = Collaboration::query()->find($collaborationId);

        if ($collaboration === null) {
            return;
        }

        $actor = null;
        if (property_exists($event, 'actorProfileId') && $event->actorProfileId !== null) {
            $actor = Profile::query()->find($event->actorProfileId);
        }

        match (true) {
            $event instanceof CollaborationScheduled => $this->notificationService->notifyCollaborationScheduled($collaboration, $actor),
            $event instanceof CollaborationRescheduled => $this->notificationService->notifyCollaborationRescheduled($collaboration, $actor),
            $event instanceof CollaborationCancelled => $this->notificationService->notifyCollaborationCancelled($collaboration, $actor, $event->reason),
            default => null,
        };
    }
}
