<?php

declare(strict_types=1);

namespace App\Enums;

enum RewardClaimStatus: string
{
    case Available = 'available';
    case Redeemed = 'redeemed';
    case Expired = 'expired';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
