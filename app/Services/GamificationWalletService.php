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
use App\Services\Admin\XpEarnRuleService;
use Illuminate\Support\Facades\DB;

class GamificationWalletService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly XpEarnRuleService $xpEarnRuleService,
    ) {}

    /**
     * Award the amount stored against the given PointEventType on the
     * xp_earn_rules table to a profile. Creates a wallet if none exists.
     * Evaluates badge conditions afterwards.
     *
     * Why the lookup: the displayed "+N XP" the app shows and the value the
     * ledger writes MUST agree. They both flow through xp_earn_rules now, so
     * a single admin save changes both. See docs/plans 2026-06-01-admin-followups
     * Q (XP economy).
     */
    public function awardPoints(
        string $profileId,
        PointEventType $eventType,
        ?string $referenceId = null,
        ?string $description = null,
    ): PointLedger {
        $points = $this->xpEarnRuleService->pointsFor($eventType);

        return DB::transaction(function () use ($profileId, $points, $eventType, $referenceId, $description): PointLedger {
            $ledgerEntry = PointLedger::create([
                'profile_id' => $profileId,
                'points' => $points,
                'event_type' => $eventType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $wallet = $this->getOrCreateWallet($profileId);

            $wallet->increment('points', $points);

            // Award first-kolab bonus on the profile's very first completion.
            if ($eventType === PointEventType::CollaborationComplete) {
                $completedCount = $this->countLedgerEvents($profileId, [PointEventType::CollaborationComplete]);
                if ($completedCount === 1) {
                    $bonusPoints = $this->xpEarnRuleService->pointsFor(PointEventType::FirstKolabBonus);

                    PointLedger::create([
                        'profile_id' => $profileId,
                        'points' => $bonusPoints,
                        'event_type' => PointEventType::FirstKolabBonus,
                        'reference_id' => $referenceId,
                        'description' => 'First Kolab bonus!',
                    ]);
                    $wallet->increment('points', $bonusPoints);
                }
            }

            $this->evaluateBadges($profileId);

            return $ledgerEntry;
        });
    }

    /**
     * Credit an EXPLICIT point amount (bypassing the xp_earn_rules lookup) via
     * the same ledger + wallet + badge-evaluation path as awardPoints(). For
     * callers that already know the correct point value per-event (e.g.
     * MissionService, which stores `points` directly on the mission row).
     */
    public function creditPoints(
        string $profileId,
        PointEventType $eventType,
        int $points,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $challengeId = null,
    ): PointLedger {
        return DB::transaction(function () use ($profileId, $points, $eventType, $referenceId, $description, $challengeId): PointLedger {
            $ledgerEntry = PointLedger::create([
                'profile_id' => $profileId,
                'points' => $points,
                'event_type' => $eventType,
                'reference_id' => $referenceId,
                'challenge_id' => $challengeId,
                'description' => $description,
            ]);

            $wallet = $this->getOrCreateWallet($profileId);

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
        // Fetch every already-earned slug in one query, then skip them before
        // running any per-badge condition check. Once all badges are earned
        // this short-circuits without touching the wallet or the ledger.
        $earnedSlugs = EarnedBadge::query()
            ->where('profile_id', $profileId)
            ->pluck('badge_slug')
            ->all();

        $unearned = array_filter(
            GamificationBadgeSlug::cases(),
            static fn (GamificationBadgeSlug $slug): bool => ! in_array($slug, $earnedSlugs, true),
        );

        if ($unearned === []) {
            return;
        }

        $wallet = Wallet::query()->where('profile_id', $profileId)->first();

        foreach ($unearned as $badgeSlug) {
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

        $this->notificationService->createLocalizedNotification(
            recipient: $profile,
            type: NotificationType::GamificationBadgeEarned,
            titleKey: 'notifications.badge.gamification_earned.title',
            bodyKey: 'notifications.badge.gamification_earned.body',
            replace: ['badge' => $badgeSlug->displayName()],
            targetId: $badgeId,
            targetType: 'earned_badge',
        );
    }
}
