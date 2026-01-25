<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AcceptApplicationRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date', 'after:today'],
            'contact_methods' => ['required', 'array'],
            'contact_methods.whatsapp' => ['nullable', 'string'],
            'contact_methods.email' => ['nullable', 'email'],
            'contact_methods.instagram' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $contactMethods = $this->input('contact_methods', []);

            $hasAtLeastOneMethod = ! empty($contactMethods['whatsapp'])
                || ! empty($contactMethods['email'])
                || ! empty($contactMethods['instagram']);

            if (! $hasAtLeastOneMethod) {
                $validator->errors()->add(
                    'contact_methods',
                    __('At least one contact method must be provided')
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_date.required' => __('validation.required', ['attribute' => 'scheduled date']),
            'scheduled_date.date' => __('validation.date', ['attribute' => 'scheduled date']),
            'scheduled_date.after' => __('validation.after', ['attribute' => 'scheduled date', 'date' => 'today']),
            'contact_methods.required' => __('validation.required', ['attribute' => 'contact methods']),
            'contact_methods.array' => __('validation.array', ['attribute' => 'contact methods']),
            'contact_methods.email.email' => __('validation.email', ['attribute' => 'contact email']),
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
