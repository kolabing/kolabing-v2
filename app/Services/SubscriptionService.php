<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidReferralCodeException;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly ReferralService $referralService,
    ) {}

    /**
     * Get the subscription for a business profile.
     */
    public function getSubscription(Profile $profile): ?BusinessSubscription
    {
        if (! $profile->isBusiness()) {
            return null;
        }

        return $profile->subscription;
    }

    /**
     * Activate (upsert) a business subscription from a completed Checkout Session.
     * Idempotent: one row per profile (unique `profile_id`). Rewards the referral
     * on the first paid subscription when a valid code rode on the session metadata.
     *
     * Returns the upserted row so the synchronous return-from-Stripe confirmation
     * can answer with it; the webhook ignores the return value.
     */
    public function activateFromStripeSession(Session $session): ?BusinessSubscription
    {
        $profileId = StripeService::sessionProfileId($session);

        if ($profileId === null) {
            Log::warning('Stripe checkout.session.completed without a profile reference', [
                'session_id' => $session->id ?? null,
            ]);

            return null;
        }

        $profile = Profile::find($profileId);

        if ($profile === null || ! $profile->isBusiness()) {
            return null;
        }

        $stripeSubscriptionId = StripeService::sessionSubscriptionId($session);

        if (blank($stripeSubscriptionId)) {
            return null;
        }

        $stripeSubscription = $this->stripeService->retrieveSubscription($stripeSubscriptionId);

        return DB::transaction(function () use ($profile, $session, $stripeSubscription): BusinessSubscription {
            $subscription = BusinessSubscription::query()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'stripe_customer_id' => StripeService::sessionCustomerId($session),
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'status' => $this->mapStripeStatus((string) $stripeSubscription->status),
                    'source' => SubscriptionSource::Stripe,
                    'current_period_start' => $this->timestamp(StripeService::periodStart($stripeSubscription)),
                    'current_period_end' => $this->timestamp(StripeService::periodEnd($stripeSubscription)),
                    'cancel_at_period_end' => (bool) $stripeSubscription->cancel_at_period_end,
                ],
            );

            $code = $session->metadata['referral_code'] ?? null;

            if (! blank($code) && $subscription->isActive()) {
                try {
                    $referralCode = $this->referralService->validateCodeForProfile($profile, (string) $code);
                    $this->referralService->rewardFirstPaidSubscription($profile, $subscription, $referralCode);
                } catch (InvalidReferralCodeException|\RuntimeException $e) {
                    Log::warning('Stripe referral reward skipped', [
                        'profile_id' => $profile->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $subscription;
        });
    }

    /**
     * Keep a subscription's status/period in sync from customer.subscription.* events.
     */
    public function syncFromStripeSubscription(StripeSubscription $stripeSubscription): void
    {
        $subscription = BusinessSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->first();

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => $this->mapStripeStatus((string) $stripeSubscription->status),
            'current_period_start' => $this->timestamp(StripeService::periodStart($stripeSubscription)) ?? $subscription->current_period_start,
            'current_period_end' => $this->timestamp(StripeService::periodEnd($stripeSubscription)) ?? $subscription->current_period_end,
            'cancel_at_period_end' => (bool) $stripeSubscription->cancel_at_period_end,
        ]);
    }

    private function mapStripeStatus(string $stripeStatus): SubscriptionStatus
    {
        return match ($stripeStatus) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled', 'incomplete_expired' => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Inactive,
        };
    }

    private function timestamp(?int $unix): ?Carbon
    {
        return $unix !== null ? Carbon::createFromTimestamp($unix) : null;
    }
}
