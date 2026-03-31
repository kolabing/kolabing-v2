<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GamificationBadgeSlug;
use App\Enums\NotificationType;
use App\Enums\PointEventType;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class GamificationWalletService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Award points to a profile. Creates wallet if none exists.
     * Evaluates badge conditions after awarding.
     */
    public function awardPoints(
        string $profileId,
        int $points,
        PointEventType $eventType,
        ?string $referenceId = null,
        ?string $description = null,
    ): PointLedger {
        return DB::transaction(function () use ($profileId, $points, $eventType, $referenceId, $description): PointLedger {
            $ledgerEntry = PointLedger::create([
                'profile_id' => $profileId,
                'points' => $points,
                'event_type' => $eventType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $wallet = Wallet::query()->firstOrCreate(
                ['profile_id' => $profileId],
                ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false]
            );

            $wallet->increment('points', $points);

            $this->evaluateBadges($profileId);

            return $ledgerEntry;
        });
    }

    /**
     * Get or create a wallet for the given profile.
     */
    public function getOrCreateWallet(string $profileId): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['profile_id' => $profileId],
            ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false]
        );
    }

    /**
     * Evaluate all badge conditions for a profile and award any newly earned badges.
     */
    public function evaluateBadges(string $profileId): void
    {
        $wallet = Wallet::query()->where('profile_id', $profileId)->first();

        foreach (GamificationBadgeSlug::cases() as $badgeSlug) {
            $alreadyEarned = EarnedBadge::query()
                ->where('profile_id', $profileId)
                ->where('badge_slug', $badgeSlug)
                ->exists();

            if ($alreadyEarned) {
                continue;
            }

            if ($this->isBadgeConditionMet($profileId, $badgeSlug, $wallet)) {
                $badge = EarnedBadge::create([
                    'profile_id' => $profileId,
                    'badge_slug' => $badgeSlug,
                    'earned_at' => now(),
                ]);

                $this->notifyBadgeEarned($profileId, $badgeSlug, $badge->id);
            }
        }
    }

    /**
     * Check if a specific badge condition is met.
     */
    private function isBadgeConditionMet(string $profileId, GamificationBadgeSlug $badge, ?Wallet $wallet): bool
    {
        return match ($badge) {
            GamificationBadgeSlug::FirstKolab => $this->countLedgerEvents($profileId, [PointEventType::CollaborationComplete]) >= 1,
            GamificationBadgeSlug::ContentCreator => $this->countLedgerEvents($profileId, [PointEventType::ReviewPosted, PointEventType::UgcPosted]) >= 3,
            GamificationBadgeSlug::CommunityEarner => ($wallet?->points ?? 0) >= 100,
            GamificationBadgeSlug::ReferralPioneer => $this->countLedgerEvents($profileId, [PointEventType::ReferralConversion]) >= 1,
            GamificationBadgeSlug::PowerPartner => $this->countLedgerEvents($profileId, [PointEventType::CollaborationComplete]) >= 5,
        };
    }

    /**
     * Count ledger entries for specific event types.
     *
     * @param  array<PointEventType>  $eventTypes
     */
    private function countLedgerEvents(string $profileId, array $eventTypes): int
    {
        return PointLedger::query()
            ->where('profile_id', $profileId)
            ->whereIn('event_type', $eventTypes)
            ->count();
    }

    /**
     * Send push notification when a badge is earned.
     */
    private function notifyBadgeEarned(string $profileId, GamificationBadgeSlug $badgeSlug, string $badgeId): void
    {
        $profile = Profile::find($profileId);

        if (! $profile) {
            return;
        }

        $this->notificationService->createNotification(
            recipient: $profile,
            type: NotificationType::GamificationBadgeEarned,
            title: 'Badge Earned!',
            body: "You earned the \"{$badgeSlug->displayName()}\" badge!",
            targetId: $badgeId,
            targetType: 'earned_badge',
        );
    }
}
