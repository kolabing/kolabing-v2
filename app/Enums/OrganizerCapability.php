<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A maintainer-grantable organizer capability, independent from
 * {@see \App\Models\Profile::hasActiveSubscription()}. MVP ships a single
 * capability (publishing a Multi-Kolab Event); the enum leaves room to add
 * capabilities later without a schema change.
 */
enum OrganizerCapability: string
{
    case EventCreator = 'event_creator';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
