<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Concerns;

use App\Enums\VerificationStatus;
use App\Models\CommunityProfile;
use Illuminate\Http\Request;

/**
 * Shared serialisation of the community verification contract.
 *
 * SHARED CONTRACT with the mobile app:
 *   - is_verified           bool   (status == verified) — ALWAYS present
 *   - verification_status   string — ALWAYS present
 *   - verification_channels array  — only for the OWNER (or an admin context)
 *   - verification_flagged_at      — admin context only
 */
trait EmitsVerificationFields
{
    /**
     * Build the verification payload for a given community profile.
     *
     * @return array<string, mixed>
     */
    protected function verificationFields(?CommunityProfile $communityProfile, Request $request, ?string $ownerProfileId = null): array
    {
        $status = $communityProfile?->verification_status ?? VerificationStatus::Unverified->value;

        $payload = [
            'is_verified' => $status === VerificationStatus::Verified->value,
            'verification_status' => $status,
        ];

        if ($communityProfile === null) {
            return $payload;
        }

        if ($this->viewerOwnsCommunity($request, $ownerProfileId ?? $communityProfile->profile_id)) {
            $payload['verification_channels'] = $communityProfile->verification_channels ?? [];
            $payload['verification_flagged_at'] = $communityProfile->verification_flagged_at?->toIso8601String();
            $payload['rejection_reason'] = $communityProfile->rejection_reason;
        }

        return $payload;
    }

    /**
     * Whether the authenticated viewer owns the community (owner == admin context
     * for the API; the admin Blade panel has its own privileged surface).
     */
    private function viewerOwnsCommunity(Request $request, ?string $ownerProfileId): bool
    {
        $viewer = $request->user();

        return $viewer !== null && $ownerProfileId !== null && $viewer->id === $ownerProfileId;
    }
}
