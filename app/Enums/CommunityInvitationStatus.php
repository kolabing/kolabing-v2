<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a pending email invitation to a community.
 */
enum CommunityInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
