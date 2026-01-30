<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\Subscription;

class SubscriptionService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

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
     * @return array{checkout_url: string, session_id: string}
     */
    public function createCheckoutSession(
        Profile $profile,
        string $successUrl,
        string $cancelUrl
    ): array {
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can subscribe'));
        }

        $subscription = $profile->subscription;
        $stripeCustomerId = $subscription?->stripe_customer_id;

        if (! $stripeCustomerId) {
            $customer = Customer::create([
                'email' => $profile->email,
                'metadata' => [
                    'profile_id' => $profile->id,
                    'user_type' => 'business',
                ],
            ]);
            $stripeCustomerId = $customer->id;

            if ($subscription) {
                $subscription->update(['stripe_customer_id' => $stripeCustomerId]);
            } else {
                BusinessSubscription::query()->create([
                    'profile_id' => $profile->id,
                    'stripe_customer_id' => $stripeCustomerId,
                    'status' => SubscriptionStatus::Inactive,
                    'cancel_at_period_end' => false,
                ]);
            }
        }

        $session = CheckoutSession::create([
            'customer' => $stripeCustomerId,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => config('services.stripe.monthly_price_id'),
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'profile_id' => $profile->id,
            ],
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /**
     * Get the Stripe billing portal URL.
     *
     * @return array{portal_url: string}
     */
    public function getBillingPortalUrl(Profile $profile, string $returnUrl): array
    {
        if (! $profile->isBusiness()) {
            throw new \InvalidArgumentException(__('Only business users can access billing portal'));
        }

        $subscription = $profile->subscription;

        if (! $subscription || ! $subscription->stripe_customer_id) {
            throw new \RuntimeException(__('No subscription found for this user'));
        }

        $session = BillingPortalSession::create([
            'customer' => $subscription->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return [
            'portal_url' => $session->url,
        ];
    }

    /**
     * Cancel the subscription at period end.
     */
    public function cancelSubscription(Profile $profile): BusinessSubscription
    {
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

        Subscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $subscription->update([
            'cancel_at_period_end' => true,
        ]);

        return $subscription->fresh();
    }

    /**
     * Reactivate a cancelled subscription (remove cancel_at_period_end).
     */
    public function reactivateSubscription(Profile $profile): BusinessSubscription
    {
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

        Subscription::update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        $subscription->update([
            'cancel_at_period_end' => false,
        ]);

        return $subscription->fresh();
    }

    /**
     * Handle the checkout.session.completed webhook event.
     * Activates the subscription after successful payment.
     */
    public function handleCheckoutCompleted(CheckoutSession $session): void
    {
        $profileId = $session->metadata->profile_id ?? null;

        if (! $profileId) {
            return;
        }

        $subscription = BusinessSubscription::query()
            ->where('profile_id', $profileId)
            ->first();

        if (! $subscription) {
            return;
        }

        $stripeSubscriptionId = $session->subscription;

        if (! $stripeSubscriptionId) {
            return;
        }

        $stripeSubscription = $this->retrieveStripeSubscription((string) $stripeSubscriptionId);

        $subscription->update([
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
        ]);
    }

    /**
     * Retrieve a Stripe subscription by ID.
     * Extracted for testability.
     */
    protected function retrieveStripeSubscription(string $id): Subscription
    {
        return Subscription::retrieve($id);
    }

    /**
     * Handle the customer.subscription.updated webhook event.
     * Syncs subscription status and period dates.
     */
    public function handleSubscriptionUpdated(Subscription $stripeSubscription): void
    {
        $subscription = BusinessSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => $this->mapStripeStatus($stripeSubscription->status),
            'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
        ]);
    }

    /**
     * Handle the customer.subscription.deleted webhook event.
     * Marks subscription as cancelled.
     */
    public function handleSubscriptionDeleted(Subscription $stripeSubscription): void
    {
        $subscription = BusinessSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Handle the invoice.payment_failed webhook event.
     * Marks subscription as past due.
     */
    public function handlePaymentFailed(string $stripeSubscriptionId): void
    {
        $subscription = BusinessSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
        ]);
    }

    /**
     * Map Stripe subscription status to local SubscriptionStatus enum.
     */
    private function mapStripeStatus(string $stripeStatus): SubscriptionStatus
    {
        return match ($stripeStatus) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled', 'unpaid', 'incomplete_expired' => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Inactive,
        };
    }
}
