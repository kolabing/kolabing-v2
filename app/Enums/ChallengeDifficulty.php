<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function points(): int
    {
        return match ($this) {
            self::Easy => 5,
            self::Medium => 15,
            self::Hard => 30,
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
