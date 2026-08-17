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
        $session = $this->client()->checkout->sessions->create(
            $this->checkoutSessionParams($profile, $plan, $successUrl, $cancelUrl, $referralCode),
        );

        return (string) $session->url;
    }

    /**
     * Build the Checkout Session payload. Split out from the SDK call so the
     * commercial details (which price, promo codes, customer reuse) are testable
     * without mocking the Stripe client.
     *
     * @return array<string, mixed>
     */
    public function checkoutSessionParams(
        Profile $profile,
        string $plan,
        string $successUrl,
        string $cancelUrl,
        ?string $referralCode,
    ): array {
        $priceId = config("subscriptions.business.stripe.{$plan}.stripe_price_id");

        if (blank($priceId)) {
            throw new \RuntimeException("No Stripe price configured for plan [{$plan}].");
        }

        $metadata = array_filter([
            'profile_id' => $profile->id,
            'referral_code' => $referralCode,
        ], static fn ($value): bool => $value !== null);

        return [
            'mode' => 'subscription',
            'line_items' => [['price' => (string) $priceId, 'quantity' => 1]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $profile->id,
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
            // Lets sales run discount campaigns from the Stripe dashboard. This is
            // NOT the referral code (which rewards the referrer, not the buyer).
            'allow_promotion_codes' => true,
            'locale' => self::checkoutLocale(),
            ...$this->customerIdentity($profile),
        ];
    }

    /**
     * Reuse the profile's Stripe customer when it has one, otherwise pre-fill the
     * email. Sending both is rejected by Stripe. Without this a repeat buyer gets a
     * second customer record, which orphans the Billing Portal lookup.
     *
     * @return array{customer?: string, customer_email?: string}
     */
    private function customerIdentity(Profile $profile): array
    {
        $customerId = $profile->subscription?->stripe_customer_id;

        if (filled($customerId)) {
            return ['customer' => (string) $customerId];
        }

        return filled($profile->email) ? ['customer_email' => (string) $profile->email] : [];
    }

    /**
     * Map the app locale onto a Stripe Checkout locale. Stripe has no Catalan
     * locale, so `ca` falls back to Spanish — the nearest supported language for
     * that audience. Anything else lets Stripe negotiate from the browser.
     */
    private static function checkoutLocale(): string
    {
        return match (app()->getLocale()) {
            'en' => 'en',
            'es', 'ca' => 'es',
            default => 'auto',
        };
    }

    /**
     * Retrieve a Checkout Session so the return-from-Stripe page can confirm the
     * purchase synchronously instead of waiting on the webhook.
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * A Checkout Session counts as paid once Stripe has collected payment. Both
     * flags are checked because a zero-amount (100%-off coupon) session completes
     * with `payment_status = no_payment_required`.
     */
    public static function sessionIsPaid(Session $session): bool
    {
        return in_array($session->payment_status, ['paid', 'no_payment_required'], true)
            || $session->status === 'complete';
    }

    /**
     * The profile the session was created for. `client_reference_id` is set at
     * creation; metadata is the fallback for sessions created before it was.
     */
    public static function sessionProfileId(Session $session): ?string
    {
        $profileId = $session->client_reference_id ?: ($session->metadata['profile_id'] ?? null);

        return blank($profileId) ? null : (string) $profileId;
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
