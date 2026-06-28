<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Exceptions\InvalidReferralCodeException;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Services\Admin\XpEarnRuleService;
use Illuminate\Database\QueryException;

class ReferralService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
        private readonly XpEarnRuleService $xpEarnRuleService,
        private readonly MissionService $missionService,
    ) {}

    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @throws InvalidReferralCodeException
     */
    public function validateCodeForProfile(Profile $profile, string $referralCode): ReferralCode
    {
        $normalizedCode = $this->normalizeCode($referralCode);

        if ($normalizedCode === null) {
            throw new InvalidReferralCodeException('Referral code is invalid.');
        }

        /** @var ReferralCode|null $code */
        $code = ReferralCode::query()
            ->where('code', $normalizedCode)
            ->first();

        if (! $code) {
            throw new InvalidReferralCodeException('Referral code is invalid.');
        }

        if ($code->expires_at !== null && $code->expires_at->isPast()) {
            throw new InvalidReferralCodeException('Referral code is invalid.');
        }

        if ($code->profile_id === $profile->id) {
            throw new InvalidReferralCodeException('Referral code is invalid.');
        }

        $alreadyUsed = ReferralRedemption::query()
            ->where('referred_profile_id', $profile->id)
            ->exists();

        if ($alreadyUsed) {
            throw new InvalidReferralCodeException('Referral code is invalid.');
        }

        return $code;
    }

    public function rewardFirstPaidSubscription(
        Profile $referredProfile,
        BusinessSubscription $subscription,
        ReferralCode $referralCode,
    ): bool {
        if (! $subscription->isActive()) {
            return false;
        }

        try {
            ReferralRedemption::query()->create([
                'referral_code_id' => $referralCode->id,
                'referred_profile_id' => $referredProfile->id,
                'business_subscription_id' => $subscription->id,
                'rewarded_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return false;
            }

            throw $e;
        }

        $points = $this->xpEarnRuleService->pointsFor(PointEventType::ReferralConversion);

        $this->walletService->awardPoints(
            $referralCode->profile_id,
            PointEventType::ReferralConversion,
            $subscription->id,
            'Referral subscription conversion',
        );

        $referralCode->increment('total_conversions');
        $referralCode->increment('total_points_earned', $points);

        // Mission progress for the referrer's business_referred missions
        // (business + community both have a "refer a business" mission).
        // Guarded so a mission failure never reverses the reward.
        $referrer = Profile::find($referralCode->profile_id);
        if ($referrer !== null) {
            $this->missionService->recordSafely(
                $referrer,
                MissionTrigger::BusinessReferred,
                1,
                ['reference_id' => $subscription->id],
            );
        }

        return true;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'constraint');
    }
}
