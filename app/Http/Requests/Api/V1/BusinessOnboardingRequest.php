<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BusinessOnboardingRequest extends FormRequest
{
    /**
     * Valid business types.
     *
     * @var array<string>
     */
    public const BUSINESS_TYPES = [
        'cafe',
        'restaurant',
        'bar',
        'bakery',
        'coworking',
        'gym',
        'salon',
        'retail',
        'hotel',
        'other',
    ];

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
            'name' => ['required', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:1000'],
            'business_type' => ['required', 'string', 'in:'.implode(',', self::BUSINESS_TYPES)],
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'instagram' => ['nullable', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9._]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'string'],
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
            'name.required' => __('The name field is required'),
            'name.max' => __('The name must not exceed 255 characters'),
            'about.max' => __('The about description must not exceed 1000 characters'),
            'business_type.required' => __('The business type field is required'),
            'business_type.in' => __('The selected business type is invalid'),
            'city_id.required' => __('The city field is required'),
            'city_id.uuid' => __('The city ID must be a valid UUID'),
            'city_id.exists' => __('The selected city does not exist'),
            'phone_number.regex' => __('The phone number format is invalid. Use E.164 format (e.g., +34612345678)'),
            'instagram.regex' => __('The instagram handle format is invalid'),
            'website.url' => __('The website must be a valid URL'),
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
