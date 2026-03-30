<?php

declare(strict_types=1);

namespace App\Enums;

enum PointEventType: string
{
    case CollaborationComplete = 'collaboration_complete';
    case ReviewPosted = 'review_posted';
    case UgcPosted = 'ugc_posted';
    case ReferralConversion = 'referral_conversion';
    case Withdrawal = 'withdrawal';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the default points for this event type.
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::CollaborationComplete => 1,
            self::ReviewPosted => 1,
            self::UgcPosted => 1,
            self::ReferralConversion => 50,
            self::Withdrawal => 0,
        };
    }
}
