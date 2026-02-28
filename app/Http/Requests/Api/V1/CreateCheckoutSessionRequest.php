<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'success_url' => ['required', 'string', 'regex:/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\/.+/'],
            'cancel_url' => ['required', 'string', 'regex:/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\/.+/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'success_url.regex' => __('The success URL must be a valid URL (e.g. https://example.com or kolabing://path).'),
            'cancel_url.regex' => __('The cancel URL must be a valid URL (e.g. https://example.com or kolabing://path).'),
        ];
    }

    /**
     * Get the success URL.
     */
    public function getSuccessUrl(): string
    {
        return $this->input('success_url');
    }

    /**
     * Get the cancel URL.
     */
    public function getCancelUrl(): string
    {
        return $this->input('cancel_url');
    }
}
