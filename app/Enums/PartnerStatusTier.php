<?php

declare(strict_types=1);

namespace App\Enums;

enum PartnerStatusTier: string
{
    case NewPartner = 'new_partner';
    case ActivePartner = 'active_partner';
    case TrustedPartner = 'trusted_partner';
    case CommunityFavourite = 'community_favourite';

    public function label(): string
    {
        return match ($this) {
            self::NewPartner => 'New Partner',
            self::ActivePartner => 'Active Partner',
            self::TrustedPartner => 'Trusted Partner',
            self::CommunityFavourite => 'Community Favourite',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NewPartner, self::ActivePartner => '',
            self::TrustedPartner => '✓',
            self::CommunityFavourite => '★',
        };
    }
}
