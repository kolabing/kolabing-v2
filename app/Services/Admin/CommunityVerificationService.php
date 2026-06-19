<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\NotificationType;
use App\Enums\VerificationStatus;
use App\Models\CommunityProfile;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;

/**
 * Admin-side community verification actions, mirroring the maintainer surface
 * pattern (e.g. subscription grant/revoke). All state lives on community_profiles.
 */
class CommunityVerificationService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Mark a community as verified. Clears any "channels changed" flag and the
     * rejection reason, stamps verified_at / verified_by, and notifies the
     * community owner ("You got verified").
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

        $owner = $communityProfile->loadMissing('profile')->profile;
        if ($owner !== null) {
            $this->notifications->createNotification(
                recipient: $owner,
                type: NotificationType::CommunityVerified,
                title: __('You got verified ✅'),
                body: __('Your community is now verified. Businesses will see your verified badge.'),
                targetId: $communityProfile->id,
                targetType: 'community_profile',
            );
        }

        return $communityProfile;
    }

    /**
     * Reject a community's verification with a reason, and notify the owner.
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

        $owner = $communityProfile->loadMissing('profile')->profile;
        if ($owner !== null) {
            $this->notifications->createNotification(
                recipient: $owner,
                type: NotificationType::CommunityVerificationRejected,
                title: __('Verification needs changes'),
                body: __('Your community verification was not approved: :reason', ['reason' => $reason]),
                targetId: $communityProfile->id,
                targetType: 'community_profile',
            );
        }

        return $communityProfile;
    }
}
