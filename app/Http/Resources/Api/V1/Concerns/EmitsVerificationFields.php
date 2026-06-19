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
 *   - public_channels       array  — ALWAYS present; only the channels the
 *                                    community marked is_public, each as
 *                                    { type, url } (NO is_public, NO private items)
 *   - verification_channels array  — only for the OWNER; full list, each item
 *                                    { type, url, is_public }
 *   - verification_flagged_at      — OWNER context only
 *   - rejection_reason             — OWNER context only
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
            // ALWAYS present: only the channels the community marked public, each
            // as { type, url }. Empty array when none / no profile.
            'public_channels' => $communityProfile?->publicChannels() ?? [],
        ];

        if ($communityProfile === null) {
            return $payload;
        }

        // The OWNER additionally gets the full channel list (with is_public per
        // item) plus the private admin-review fields, so their own profile screen
        // can edit visibility while the compact widget still shows public icons.
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
