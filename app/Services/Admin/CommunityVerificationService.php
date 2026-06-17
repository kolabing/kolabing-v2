<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\VerificationStatus;
use App\Models\CommunityProfile;
use Illuminate\Support\Carbon;

/**
 * Admin-side community verification actions, mirroring the maintainer surface
 * pattern (e.g. subscription grant/revoke). All state lives on community_profiles.
 */
class CommunityVerificationService
{
    /**
     * Mark a community as verified. Clears any "channels changed" flag and the
     * rejection reason, stamps verified_at / verified_by.
     */
    public function verify(CommunityProfile $communityProfile, ?string $adminId): CommunityProfile
    {
        $communityProfile->update([
            'verification_status' => VerificationStatus::Verified->value,
            'verified_at' => Carbon::now(),
            'verified_by' => $adminId,
            'verification_flagged_at' => null,
            'rejection_reason' => null,
        ]);

        return $communityProfile;
    }

    /**
     * Reject a community's verification with a reason.
     */
    public function reject(CommunityProfile $communityProfile, string $reason, ?string $adminId): CommunityProfile
    {
        $communityProfile->update([
            'verification_status' => VerificationStatus::Rejected->value,
            'rejection_reason' => $reason,
            'verified_at' => null,
            'verified_by' => $adminId,
            'verification_flagged_at' => null,
        ]);

        return $communityProfile;
    }
}
