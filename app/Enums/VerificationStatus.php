<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Community verification lifecycle.
 *
 * unverified → pending (channels submitted) → verified | rejected (admin action).
 * A verified community that edits its channels stays verified but is flagged for
 * admin re-check (community_profiles.verification_flagged_at), it is NOT reset.
 */
enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label for the admin dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Pending => 'Pending review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Bootstrap badge colour for the admin dashboard.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
            self::Unverified => 'secondary',
        };
    }
}
