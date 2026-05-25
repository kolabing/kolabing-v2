<?php

declare(strict_types=1);

namespace App\Enums;

enum GamificationBadgeSlug: string
{
    case FirstKolab = 'first_kolab';
    case ContentCreator = 'content_creator';
    case CommunityEarner = 'community_earner';
    case ReferralPioneer = 'referral_pioneer';
    case PowerPartner = 'power_partner';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function displayName(): string
    {
        return match ($this) {
            self::FirstKolab => 'First Kolab',
            self::ContentCreator => 'Storyteller',
            self::CommunityEarner => 'Momentum Club',
            self::ReferralPioneer => 'Referral Pioneer',
            self::PowerPartner => 'Trusted Voice',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FirstKolab => 'Completed your first collaboration',
            self::ContentCreator => 'Share your story - post 3 collaboration reviews',
            self::CommunityEarner => 'Keep the momentum - reach 100 XP',
            self::ReferralPioneer => 'Grow the community - refer your first partner',
            self::PowerPartner => 'A trusted voice - complete 5 Kolabs',
        };
    }
}
