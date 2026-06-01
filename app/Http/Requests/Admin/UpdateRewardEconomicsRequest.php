<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRewardEconomicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'referral_goal' => ['required', 'integer', 'between:1,100'],
            'referral_cash_reward_cents' => ['required', 'integer', 'between:0,1000000'],
            'euro_cents_per_point' => ['required', 'integer', 'between:1,500'],
            'withdrawal_threshold_cents' => ['required', 'integer', 'between:0,1000000'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'euro_cents_per_point.between' => 'Per-point rate must be between 1 cent (€0.01) and 500 cents (€5.00).',
            'currency.size' => 'Currency must be a 3-letter ISO code (e.g. EUR).',
        ];
    }
}
