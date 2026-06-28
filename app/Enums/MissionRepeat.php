<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How often a mission can be re-earned. Stored on
 * `challenges.repeat_interval` (default `once`). The repeat bucket drives the
 * `challenge_progress.period_key` (`once` / `2026-06` / `2026-W25` /
 * `2026-06-22` / season key).
 */
enum MissionRepeat: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Seasonal = 'seasonal';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
