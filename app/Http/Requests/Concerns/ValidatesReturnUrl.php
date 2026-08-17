<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Closure;

trait ValidatesReturnUrl
{
    /**
     * A validation rule for a Stripe return URL. The URL must be the app deep-link
     * scheme (`kolabing://`) or an allowlisted https host, so a Stripe redirect
     * (checkout success/cancel, billing-portal return) cannot be pointed at an
     * arbitrary domain (open-redirect / phishing hardening).
     */
    protected function returnUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $value = (string) $value;

            if (str_starts_with($value, 'kolabing://')) {
                return;
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);
            $host = parse_url($value, PHP_URL_HOST);

            if ($scheme === 'https' && is_string($host) && $host !== '') {
                // Normalise both sides (lowercase, strip a FQDN trailing dot) so a
                // legitimate mixed-case host is not rejected, while the exact-match
                // compare still blocks userinfo/subdomain open-redirect tricks.
                $host = rtrim(strtolower($host), '.');

                // The web app builds its return URLs from the host it is served on,
                // so that host is always legitimate — include it by construction
                // rather than relying on STRIPE_ALLOWED_RETURN_HOSTS being edited in
                // step with WEBAPP_HOST (drift there fails every checkout with a 422
                // the buyer sees only as "could not start checkout").
                $allowed = array_map(
                    static fn ($allowedHost): string => rtrim(strtolower((string) $allowedHost), '.'),
                    array_filter([
                        ...(array) config('services.stripe.allowed_return_hosts', []),
                        config('webapp.host'),
                    ]),
                );

                if (in_array($host, $allowed, true)) {
                    return;
                }
            }

            $fail(__('The :attribute is not an allowed return URL.', ['attribute' => $attribute]));
        };
    }
}
