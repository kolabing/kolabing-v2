<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Enums\NotificationType;
use App\Models\Profile;
use App\Services\Notifications\GrowthAudienceService;
use App\Services\Notifications\GrowthRateLimitService;
use App\Services\NotificationService;
use App\Support\Notifications\NotificationMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendGrowthCampaignNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        GrowthAudienceService $audienceService,
        GrowthRateLimitService $rateLimitService,
        NotificationService $notificationService,
        NotificationMetrics $metrics,
    ): void {
        $campaigns = [
            [
                'type' => NotificationType::PendingApplicationNudge,
                'payloads' => $audienceService->pendingApplicationNudges(),
            ],
            [
                'type' => NotificationType::OpportunityMatch,
                'payloads' => $audienceService->opportunityMatches(),
            ],
            [
                'type' => NotificationType::NearbyEventMatch,
                'payloads' => $audienceService->nearbyEventMatches(),
            ],
            [
                'type' => NotificationType::WalletThresholdReached,
                'payloads' => $audienceService->walletThresholdReached(),
            ],
            [
                'type' => NotificationType::DormantUserReactivation,
                'payloads' => $audienceService->dormantUserReactivation(),
            ],
        ];

        foreach ($campaigns as $campaign) {
            /** @var NotificationType $type */
            $type = $campaign['type'];
            $payloads = $campaign['payloads'];

            if (! $notificationService->isEnabled($type)) {
                continue;
            }

            foreach ($payloads as $payload) {
                /** @var Profile $recipient */
                $recipient = $payload['recipient'];
                $preferences = $recipient->notificationPreferences()->first();

                if (! $preferences?->marketing_enabled) {
                    $metrics->recordSkipped($type, 'marketing_disabled', [
                        'profile_id' => $recipient->id,
                    ]);

                    continue;
                }

                if (! $rateLimitService->canSend($recipient, $type)) {
                    $metrics->recordSkipped($type, 'rate_limited', [
                        'profile_id' => $recipient->id,
                    ]);

                    continue;
                }

                $notificationService->createNotification(
                    recipient: $recipient,
                    type: $type,
                    title: $payload['title'],
                    body: $payload['body'],
                    targetId: $payload['targetId'],
                    targetType: $payload['targetType'],
                    dedupeKey: $payload['dedupeKey'],
                    deeplink: $payload['deeplink'],
                    data: $payload['data'],
                );
            }
        }
    }
}
