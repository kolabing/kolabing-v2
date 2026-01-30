<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplyToOpportunityRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'availability' => ['required', 'string', 'min:20', 'max:500'],
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
            'message.required' => __('validation.required', ['attribute' => 'message']),
            'message.min' => __('validation.min.string', ['attribute' => 'message', 'min' => 20]),
            'message.max' => __('validation.max.string', ['attribute' => 'message', 'max' => 2000]),
            'availability.required' => __('validation.required', ['attribute' => 'availability']),
            'availability.min' => __('validation.min.string', ['attribute' => 'availability', 'min' => 20]),
            'availability.max' => __('validation.max.string', ['attribute' => 'availability', 'max' => 500]),
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
