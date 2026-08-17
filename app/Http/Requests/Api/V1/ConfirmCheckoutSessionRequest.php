<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCheckoutSessionRequest extends FormRequest
{
    /**
     * The route is behind auth:sanctum; the business-only check and the
     * session-ownership check both live in the controller.
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
            'session_id' => ['required', 'string', 'max:255', 'regex:/^cs_[A-Za-z0-9_]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.regex' => __('The session id is not a valid Stripe Checkout Session.'),
        ];
    }

    public function sessionId(): string
    {
        return (string) $this->input('session_id');
    }
}
