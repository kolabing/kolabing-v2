<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Illuminate\Support\Str;

class SubscriptionService
{
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
     * Create a Stripe checkout session URL for subscription.
     *
     * Note: This is a placeholder implementation. Real Stripe integration
     * will use the stripe/stripe-php package.
     *
     * @return array{checkout_url: string, session_id: string}
     */
    public function createCheckoutSession(
        Profile $profile,
        string $successUrl,
        string $cancelUrl
    ): array {
        // Ensure the profile is a business user
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can subscribe'));
        }

        // Get or create stripe customer ID
        $subscription = $profile->subscription;
        $stripeCustomerId = $subscription?->stripe_customer_id;

        if (! $stripeCustomerId) {
            // In real implementation, create Stripe customer here
            $stripeCustomerId = 'cus_'.Str::random(14);

            // Create or update the subscription record with customer ID
            if ($subscription) {
                $subscription->update(['stripe_customer_id' => $stripeCustomerId]);
            } else {
                $subscription = BusinessSubscription::create([
                    'profile_id' => $profile->id,
                    'stripe_customer_id' => $stripeCustomerId,
                    'status' => SubscriptionStatus::Inactive,
                    'cancel_at_period_end' => false,
                ]);
            }
        }

        // In real implementation, create Stripe checkout session here
        // using Stripe\Checkout\Session::create()
        $sessionId = 'cs_'.Str::random(24);

        // Placeholder checkout URL - in production this would be a real Stripe URL
        $checkoutUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Get the Stripe billing portal URL.
     *
     * Note: This is a placeholder implementation. Real Stripe integration
     * will use the stripe/stripe-php package.
     *
     * @return array{portal_url: string}
     */
    public function getBillingPortalUrl(Profile $profile, string $returnUrl): array
    {
        // Ensure the profile is a business user
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can access billing portal'));
        }

        $subscription = $profile->subscription;

        if (! $subscription || ! $subscription->stripe_customer_id) {
            throw new \RuntimeException(__('No subscription found for this user'));
        }

        // In real implementation, create Stripe billing portal session here
        // using Stripe\BillingPortal\Session::create()
        $portalUrl = "https://billing.stripe.com/p/session/{$subscription->stripe_customer_id}";

        return [
            'portal_url' => $portalUrl,
        ];
    }

    /**
     * Cancel the subscription at period end.
     *
     * Note: This is a placeholder implementation. Real Stripe integration
     * will use the stripe/stripe-php package.
     */
    public function cancelSubscription(Profile $profile): BusinessSubscription
    {
        // Ensure the profile is a business user
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can cancel subscriptions'));
        }

        $subscription = $profile->subscription;

        if (! $subscription || ! $subscription->stripe_subscription_id) {
            throw new \RuntimeException(__('No active subscription found'));
        }

        if (! $subscription->isActive()) {
            throw new \RuntimeException(__('Subscription is not active'));
        }

        // In real implementation, update Stripe subscription here
        // using Stripe\Subscription::update() with cancel_at_period_end = true

        // Update local record
        $subscription->update([
            'cancel_at_period_end' => true,
        ]);

        return $subscription->fresh();
    }

    /**
     * Reactivate a cancelled subscription (remove cancel_at_period_end).
     *
     * Note: This is a placeholder implementation. Real Stripe integration
     * will use the stripe/stripe-php package.
     */
    public function reactivateSubscription(Profile $profile): BusinessSubscription
    {
        // Ensure the profile is a business user
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can reactivate subscriptions'));
        }

        $subscription = $profile->subscription;

        if (! $subscription || ! $subscription->stripe_subscription_id) {
            throw new \RuntimeException(__('No subscription found'));
        }

        if (! $subscription->isActive()) {
            throw new \RuntimeException(__('Cannot reactivate an inactive subscription'));
        }

        if (! $subscription->cancel_at_period_end) {
            throw new \RuntimeException(__('Subscription is not scheduled for cancellation'));
        }

        // In real implementation, update Stripe subscription here
        // using Stripe\Subscription::update() with cancel_at_period_end = false

        // Update local record
        $subscription->update([
            'cancel_at_period_end' => false,
        ]);

        return $subscription->fresh();
    }
}
