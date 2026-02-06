<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\RewardClaimStatus;
use App\Models\ChallengeCompletion;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Support\Facades\DB;

class SpinWheelService
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Spin the wheel for a verified challenge completion.
     *
     * Uses probability-based random reward selection. Each reward has an independent
     * probability of being won. The spin walks through available rewards accumulating
     * probability until a winner is determined or all probabilities are exhausted.
     *
     * @return array{won: bool, reward_claim: RewardClaim|null}
     *
     * @throws \InvalidArgumentException If the completion is not verified or the caller is not the challenger.
     * @throws \LogicException If the caller has already spun for this completion.
     */
    public function spin(Profile $profile, ChallengeCompletion $completion): array
    {
        if ($completion->status !== ChallengeCompletionStatus::Verified) {
            throw new \InvalidArgumentException('Challenge completion must be verified before spinning.');
        }

        if ($completion->challenger_profile_id !== $profile->id) {
            throw new \InvalidArgumentException('You are not the challenger for this completion.');
        }

        $alreadySpun = RewardClaim::query()
            ->where('challenge_completion_id', $completion->id)
            ->where('profile_id', $profile->id)
            ->exists();

        if ($alreadySpun) {
            throw new \LogicException('You have already spun for this challenge completion.');
        }

        $availableRewards = EventReward::query()
            ->where('event_id', $completion->event_id)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($availableRewards->isEmpty()) {
            return ['won' => false, 'reward_claim' => null];
        }

        $random = mt_rand() / mt_getrandmax();
        $cumulative = 0.0;
        $selectedReward = null;

        foreach ($availableRewards as $reward) {
            $cumulative += (float) $reward->probability;
            if ($random <= $cumulative) {
                $selectedReward = $reward;
                break;
            }
        }

        if ($selectedReward === null) {
            return ['won' => false, 'reward_claim' => null];
        }

        $claim = DB::transaction(function () use ($selectedReward, $profile, $completion): ?RewardClaim {
            /** @var EventReward $lockedReward */
            $lockedReward = EventReward::query()
                ->lockForUpdate()
                ->find($selectedReward->id);

            if (! $lockedReward || $lockedReward->remaining_quantity <= 0) {
                return null;
            }

            $lockedReward->decrement('remaining_quantity');

            return RewardClaim::query()->create([
                'event_reward_id' => $lockedReward->id,
                'profile_id' => $profile->id,
                'challenge_completion_id' => $completion->id,
                'status' => RewardClaimStatus::Available,
                'won_at' => now(),
            ]);
        });

        if ($claim === null) {
            return ['won' => false, 'reward_claim' => null];
        }

        $claim->load('eventReward');

        // Send reward won notification
        $this->notificationService->notifyRewardWon($claim);

        // Check for badge milestones
        $this->badgeService->checkAndAwardBadges($profile);

        return ['won' => true, 'reward_claim' => $claim];
    }
}
