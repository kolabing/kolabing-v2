<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ValidateReferralCodeRequest extends FormRequest
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
            'referral_code' => ['required', 'string'],
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

    public function referralCode(): string
    {
        return (string) $this->input('referral_code');
    }
}
