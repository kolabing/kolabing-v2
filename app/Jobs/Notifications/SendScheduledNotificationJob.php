<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendScheduledNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $window,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $targetDate = match ($this->window) {
            '24h' => now()->addDay()->toDateString(),
            'same_day' => now()->toDateString(),
            default => null,
        };

        if ($targetDate === null) {
            return;
        }

        $collaborations = Collaboration::query()
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile',
                'creatorProfile.communityProfile',
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
            ])
            ->whereDate('scheduled_date', $targetDate)
            ->whereIn('status', [
                CollaborationStatus::Scheduled,
                CollaborationStatus::Active,
            ])
            ->get();

        foreach ($collaborations as $collaboration) {
            if ($this->window === '24h') {
                $notificationService->notifyCollaborationReminder24h($collaboration);

                continue;
            }

            $notificationService->notifyCollaborationReminderSameDay($collaboration);
        }
    }
}
