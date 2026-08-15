<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

/**
 * Thin wrapper over the Stripe SDK for the web-checkout subscription flow.
 * Isolated so controllers/services depend on this seam (and tests mock it)
 * rather than the SDK directly.
 */
class StripeService
{
    public function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * Create a subscription-mode Checkout Session and return its hosted URL.
     * `profile_id` (+ optional referral code) ride on the session + subscription
     * metadata so the webhook can link the payer and reward the referral.
     */
    public function createCheckoutSession(
        Profile $profile,
        string $plan,
        string $successUrl,
        string $cancelUrl,
        ?string $referralCode,
    ): string {
        $priceId = config("subscriptions.business.stripe.{$plan}.stripe_price_id");

        if (blank($priceId)) {
            throw new \RuntimeException("No Stripe price configured for plan [{$plan}].");
        }

        $metadata = array_filter([
            'profile_id' => $profile->id,
            'referral_code' => $referralCode,
        ], static fn ($value): bool => $value !== null);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [['price' => (string) $priceId, 'quantity' => 1]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $profile->id,
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
        ]);

        return (string) $session->url;
    }

    /**
     * Verify the webhook signature and parse the payload into an Event.
     *
     * @throws \UnexpectedValueException|\Stripe\Exception\SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            (string) config('services.stripe.webhook_secret'),
        );
    }

    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->client()->subscriptions->retrieve($subscriptionId);
    }

    /**
     * Create a Stripe Billing Portal session so a paying customer can manage or
     * cancel their subscription, then return its hosted URL. `return_url` is where
     * Stripe sends them back (validated against the return-URL allowlist upstream).
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): string
    {
        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    /**
     * Read the current-period end/start defensively: recent Stripe API versions
     * expose the period on the subscription item rather than the subscription.
     */
    public static function periodStart(Subscription $subscription): ?int
    {
        return $subscription->current_period_start
            ?? self::firstItemPeriod($subscription, 'current_period_start');
    }

    public static function periodEnd(Subscription $subscription): ?int
    {
        return $subscription->current_period_end
            ?? self::firstItemPeriod($subscription, 'current_period_end');
    }

    private static function firstItemPeriod(Subscription $subscription, string $key): ?int
    {
        $data = $subscription->items?->data ?? [];
        $item = $data[0] ?? null;

        return $item?->{$key} ?? null;
    }

    /**
     * Extract the Stripe subscription id from a Checkout Session (string or object).
     */
    public static function sessionSubscriptionId(Session $session): ?string
    {
        $subscription = $session->subscription;

        if (is_string($subscription)) {
            return $subscription;
        }

        return $subscription->id ?? null;
    }

    /**
     * Extract the Stripe customer id from a Checkout Session (string or object).
     */
    public static function sessionCustomerId(Session $session): ?string
    {
        $customer = $session->customer;

        if (is_string($customer)) {
            return $customer;
        }

        return $customer->id ?? null;
    }
}
