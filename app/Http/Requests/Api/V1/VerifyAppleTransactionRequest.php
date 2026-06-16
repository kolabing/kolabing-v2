<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\AppleSubscriptionProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyAppleTransactionRequest extends FormRequest
{
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
            'transaction_id' => ['required', 'string'],
            'original_transaction_id' => ['required', 'string'],
            'product_id' => ['required', 'string', Rule::in(AppleSubscriptionProducts::all())],
            'referral_code' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('referral_code')) {
            return;
        }

        $this->merge([
            'referral_code' => strtoupper(trim((string) $this->input('referral_code'))),
        ]);
    }

    public function referralCode(): ?string
    {
        $referralCode = $this->input('referral_code');

        return is_string($referralCode) && $referralCode !== ''
            ? $referralCode
            : null;
    }
}
