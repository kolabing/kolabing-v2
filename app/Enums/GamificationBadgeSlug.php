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
            self::ContentCreator => 'Content Creator',
            self::CommunityEarner => 'Community Earner',
            self::ReferralPioneer => 'Referral Pioneer',
            self::PowerPartner => 'Power Partner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FirstKolab => 'Completed your first collaboration',
            self::ContentCreator => 'Posted 3 reviews or pieces of content',
            self::CommunityEarner => 'Earned your first 100 points',
            self::ReferralPioneer => 'Referred a business that converted',
            self::PowerPartner => 'Completed 5 collaborations',
        };
    }
}
