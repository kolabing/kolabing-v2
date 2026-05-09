<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PointEventType;
use App\Events\Referrals\ReferralRewardEarned;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;

class ReferralService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
    ) {}

    public function rewardConversion(?string $code, Profile $convertedProfile): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $referralCode = ReferralCode::query()
            ->with('profile')
            ->where('code', $code)
            ->first();

        if ($referralCode === null || $referralCode->profile_id === $convertedProfile->id) {
            return;
        }

        $alreadyRewarded = PointLedger::query()
            ->where('profile_id', $referralCode->profile_id)
            ->where('event_type', PointEventType::ReferralConversion)
            ->where('reference_id', $convertedProfile->id)
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        $rewardPoints = PointEventType::ReferralConversion->defaultPoints();

        $this->walletService->awardPoints(
            $referralCode->profile_id,
            $rewardPoints,
            PointEventType::ReferralConversion,
            $convertedProfile->id,
            "Referral reward earned from code {$referralCode->code}"
        );

        $referralCode->increment('total_conversions');
        $referralCode->increment('total_points_earned', $rewardPoints);

        event(new ReferralRewardEarned(
            recipientProfileId: $referralCode->profile_id,
            code: $referralCode->code,
            actorProfileId: $convertedProfile->id,
            referenceId: $convertedProfile->id,
        ));
    }
}
