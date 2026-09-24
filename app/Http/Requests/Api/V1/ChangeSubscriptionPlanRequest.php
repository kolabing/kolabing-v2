<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionPlanRequest extends FormRequest
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
            'plan' => ['required', 'string', Rule::in(SubscriptionPlan::stripeCheckoutKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => __('Choose the plan to switch to.'),
            'plan.in' => __('That plan does not exist.'),
        ];
    }

    public function plan(): string
    {
        return (string) $this->validated('plan');
    }
}
