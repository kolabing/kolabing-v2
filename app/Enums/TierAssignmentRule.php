<?php

declare(strict_types=1);

namespace App\Enums;

enum TierAssignmentRule: string
{
    case Manual = 'manual';
    case XpThreshold = 'xp_threshold';
    case Tenure = 'tenure';
    case EventsAttended = 'events_attended';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether this rule needs a numeric threshold to be evaluated.
     * Manual tiers are leader-assigned and carry no threshold.
     */
    public function requiresThreshold(): bool
    {
        return $this !== self::Manual;
    }
}
