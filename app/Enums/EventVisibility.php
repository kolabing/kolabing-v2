<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an event is discoverable by any attendee (`public`) or only by active
 * members of the owning community (`members_only`). PUBLIC EVENTS lane (Batch 3).
 */
enum EventVisibility: string
{
    case Public = 'public';
    case MembersOnly = 'members_only';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
