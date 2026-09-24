<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which plan a business subscription is on (BE-NF-68).
 *
 * `Standard` is the €49 Kolabing Business plan: it unlocks the two paywalled
 * actions (ROLES §2.7). `Pro` is Venue Pro: a superset of Standard that also
 * unlocks the venue insights surface. Prices live in `config/subscriptions.php`.
 */
enum SubscriptionPlan: string
{
    case Standard = 'standard';
    case Pro = 'pro';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The checkout keys (`monthly`, `three_months`, `pro_monthly`) configured
     * for Stripe web checkout.
     *
     * @return list<string>
     */
    public static function stripeCheckoutKeys(): array
    {
        return array_keys((array) config('subscriptions.business.stripe', []));
    }

    /**
     * The plan a Stripe checkout key bills, e.g. `pro_monthly` → Pro.
     */
    public static function forCheckoutKey(string $checkoutKey): self
    {
        $plan = config("subscriptions.business.stripe.{$checkoutKey}.plan");

        return self::tryFrom((string) $plan) ?? self::Standard;
    }

    /**
     * Reverse-lookup the plan from the Stripe Price a subscription is billed on.
     * Null when the price is unknown, so a caller can keep the stored plan
     * rather than silently downgrading a customer.
     */
    public static function fromStripePriceId(?string $priceId): ?self
    {
        if (blank($priceId)) {
            return null;
        }

        foreach ((array) config('subscriptions.business.stripe', []) as $entry) {
            if (($entry['stripe_price_id'] ?? null) === $priceId) {
                return self::tryFrom((string) ($entry['plan'] ?? '')) ?? self::Standard;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Kolabing Business',
            self::Pro => 'Venue Pro',
        };
    }
}
