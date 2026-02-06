<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RewardClaimStatus;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class RewardWalletService
{
    /**
     * Get paginated reward claims for the given profile.
     */
    public function getMyRewards(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return RewardClaim::query()
            ->where('profile_id', $profile->id)
            ->with(['eventReward.event'])
            ->orderByDesc('won_at')
            ->paginate($perPage);
    }

    /**
     * Generate a unique redeem token for a reward claim.
     *
     * The token is displayed as a QR code on the attendee's device
     * and scanned by the event organizer to confirm redemption.
     *
     * @throws \InvalidArgumentException If the claim does not belong to the profile.
     * @throws \LogicException If the claim is not available or the reward has expired.
     */
    public function generateRedeemToken(Profile $profile, RewardClaim $claim): RewardClaim
    {
        if ($claim->profile_id !== $profile->id) {
            throw new \InvalidArgumentException('This reward claim does not belong to you.');
        }

        if (! $claim->isAvailable()) {
            throw new \LogicException('This reward claim is not available for redemption.');
        }

        if ($claim->eventReward->isExpired()) {
            $claim->update(['status' => RewardClaimStatus::Expired]);

            throw new \LogicException('This reward has expired.');
        }

        $claim->update([
            'redeem_token' => Str::random(64),
        ]);

        return $claim->load('eventReward');
    }

    /**
     * Confirm redemption of a reward claim by the event organizer.
     *
     * The organizer scans the attendee's QR code which contains the redeem token.
     * Only the event owner can confirm redemption.
     *
     * @throws \InvalidArgumentException If the token is invalid or the organizer is not the event owner.
     * @throws \LogicException If the claim is not available for redemption.
     */
    public function confirmRedeem(Profile $organizer, string $token): RewardClaim
    {
        $claim = RewardClaim::query()
            ->where('redeem_token', $token)
            ->with(['eventReward.event'])
            ->first();

        if (! $claim) {
            throw new \InvalidArgumentException('Invalid redeem token.');
        }

        if ($claim->eventReward->event->profile_id !== $organizer->id) {
            throw new \InvalidArgumentException('You are not the owner of this event.');
        }

        if (! $claim->isAvailable()) {
            throw new \LogicException('This reward claim is not available for redemption.');
        }

        $claim->update([
            'status' => RewardClaimStatus::Redeemed,
            'redeemed_at' => now(),
            'redeem_token' => null,
        ]);

        return $claim->load('eventReward');
    }
}
