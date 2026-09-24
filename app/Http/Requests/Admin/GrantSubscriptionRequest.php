<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A maintainer-granted subscription (ROLES §2.10). `plan` lets a maintainer
 * grant Venue Pro (BE-NF-68); left out, it grants the standard plan exactly as
 * the grant button always has.
 */
class GrantSubscriptionRequest extends FormRequest
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
            'plan' => ['sometimes', 'string', Rule::in(SubscriptionPlan::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.in' => 'Choose either the standard plan or Venue Pro.',
        ];
    }

    public function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::tryFrom((string) $this->validated('plan', SubscriptionPlan::Standard->value))
            ?? SubscriptionPlan::Standard;
    }
}
