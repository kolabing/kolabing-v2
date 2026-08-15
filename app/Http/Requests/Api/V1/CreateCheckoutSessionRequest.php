<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesReturnUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutSessionRequest extends FormRequest
{
    use ValidatesReturnUrl;

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
}
