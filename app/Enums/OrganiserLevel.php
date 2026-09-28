<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganiserLevel: string
{
    case New = 'new';
    case Rising = 'rising';
    case Trusted = 'trusted';
    case Top = 'top';

    public function rank(): int
    {
        return match ($this) {
            self::New => 0,
            self::Rising => 1,
            self::Trusted => 2,
            self::Top => 3,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::New => self::Rising,
            self::Rising => self::Trusted,
            self::Trusted => self::Top,
            self::Top => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
