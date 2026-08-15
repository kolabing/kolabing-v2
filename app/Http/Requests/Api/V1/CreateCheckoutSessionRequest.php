<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutSessionRequest extends FormRequest
{
    /**
     * The route is behind auth:sanctum; the business-only check is in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'success_url' => ['required', 'string', 'max:2048', $this->returnUrlRule()],
            'cancel_url' => ['required', 'string', 'max:2048', $this->returnUrlRule()],
            'plan' => ['sometimes', Rule::in(['monthly', 'three_months'])],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function plan(): string
    {
        $plan = (string) $this->input('plan', 'monthly');

        return in_array($plan, ['monthly', 'three_months'], true) ? $plan : 'monthly';
    }

    public function referralCode(): ?string
    {
        $code = $this->input('referral_code');

        return blank($code) ? null : (string) $code;
    }

    /**
     * Return URLs must be the app deep-link scheme (`kolabing://`) or an
     * allowlisted https host, so the Checkout return cannot be pointed at an
     * arbitrary domain (open-redirect / phishing hardening).
     */
    private function returnUrlRule(): Closure
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
                $allowed = array_map(
                    static fn ($allowedHost): string => rtrim(strtolower((string) $allowedHost), '.'),
                    (array) config('services.stripe.allowed_return_hosts', []),
                );

                if (in_array($host, $allowed, true)) {
                    return;
                }
            }

            $fail(__('The :attribute is not an allowed return URL.', ['attribute' => $attribute]));
        };
    }
}
