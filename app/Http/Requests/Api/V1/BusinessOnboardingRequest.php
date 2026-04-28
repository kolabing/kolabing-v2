<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\VenueType;
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $businessType = $this->input('business_type');
        $categories = $this->input('categories');

        if ((! is_array($categories) || $categories === []) && is_string($businessType) && $businessType !== '') {
            $this->merge([
                'categories' => [$businessType],
            ]);
        }
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
            'business_type' => ['required_without:categories', 'nullable', 'string', 'in:'.implode(',', self::BUSINESS_TYPES)],
            'categories' => ['required_without:business_type', 'array', 'min:1', 'max:3'],
            'categories.*' => ['string', 'distinct', 'in:'.implode(',', self::BUSINESS_TYPES)],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id', 'required_without:city_name'],
            'city_name' => ['nullable', 'string', 'max:100', 'required_without:city_id'],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'instagram' => ['nullable', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9._]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'string'],
            'primary_venue' => ['required', 'array'],
            'primary_venue.name' => ['required', 'string', 'max:255'],
            'primary_venue.venue_type' => ['required', 'string', 'in:'.implode(',', VenueType::values())],
            'primary_venue.capacity' => ['required', 'integer', 'min:1'],
            'primary_venue.place_id' => ['nullable', 'string', 'max:255'],
            'primary_venue.formatted_address' => ['required', 'string', 'max:500'],
            'primary_venue.city' => ['required', 'string', 'max:100'],
            'primary_venue.country' => ['nullable', 'string', 'max:100'],
            'primary_venue.latitude' => ['nullable', 'numeric'],
            'primary_venue.longitude' => ['nullable', 'numeric'],
            'primary_venue.photos' => ['nullable', 'array'],
            'primary_venue.photos.*' => ['string'],
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
            'categories.required_without' => __('At least one business category is required'),
            'categories.array' => __('The categories field must be an array'),
            'categories.min' => __('At least one business category is required'),
            'categories.max' => __('You may select up to 3 business categories'),
            'categories.*.in' => __('The selected business category is invalid'),
            'categories.*.distinct' => __('Business categories must be unique'),
            'city_id.required_without' => __('The city field is required'),
            'city_id.uuid' => __('The city ID must be a valid UUID'),
            'city_id.exists' => __('The selected city does not exist'),
            'city_name.required_without' => __('The city field is required'),
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
