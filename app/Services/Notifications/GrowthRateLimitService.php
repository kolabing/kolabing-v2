<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;
use Illuminate\Support\Carbon;

class GrowthRateLimitService
{
    public function canSend(Profile $profile, NotificationType $type, ?Carbon $at = null): bool
    {
        if (! $type->isGrowth()) {
            return true;
        }

        $at ??= now();

        return $this->countSince($profile, $at->copy()->subHours(24)) < (int) config('notifications.growth_limits.per_24_hours', 1)
            && $this->countSince($profile, $at->copy()->subDays(7)) < (int) config('notifications.growth_limits.per_7_days', 3);
    }

    public function countSince(Profile $profile, Carbon $since): int
    {
        $growthTypes = array_map(
            static fn (NotificationType $type): string => $type->value,
            array_filter(NotificationType::cases(), static fn (NotificationType $type): bool => $type->isGrowth())
        );

        return Notification::query()
            ->where('profile_id', $profile->id)
            ->whereIn('type', $growthTypes)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
