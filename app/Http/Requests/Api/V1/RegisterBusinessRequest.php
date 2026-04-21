<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\VenueType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterBusinessRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:profiles,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:1000'],
            'business_type' => ['required', 'string', 'in:'.implode(',', self::BUSINESS_TYPES)],
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
            'email.required' => __('The email field is required'),
            'email.email' => __('The email must be a valid email address'),
            'email.unique' => __('This email is already registered'),
            'password.required' => __('The password field is required'),
            'password.min' => __('The password must be at least 8 characters'),
            'password.confirmed' => __('The password confirmation does not match'),
            'name.required' => __('The name field is required'),
            'name.max' => __('The name must not exceed 255 characters'),
            'about.max' => __('The about description must not exceed 1000 characters'),
            'business_type.required' => __('The business type field is required'),
            'business_type.in' => __('The selected business type is invalid'),
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

    /**
     * Get the validated profile data.
     *
     * @return array{
     *     email: string,
     *     password: string,
     *     phone_number: string|null
     * }
     */
    public function getProfileData(): array
    {
        $validated = $this->validated();

        return [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone_number' => $validated['phone_number'] ?? null,
        ];
    }

    /**
     * Get the validated business profile data.
     *
     * @return array{
     *     name: string,
     *     about: string|null,
     *     business_type: string,
     *     city_id: string|null,
     *     city_name: string|null,
     *     instagram: string|null,
     *     website: string|null,
     *     profile_photo: string|null,
     *     primary_venue: array<string, mixed>
     * }
     */
    public function getBusinessProfileData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'about' => $validated['about'] ?? null,
            'business_type' => $validated['business_type'],
            'city_id' => $validated['city_id'] ?? null,
            'city_name' => $validated['city_name'] ?? null,
            'instagram' => $validated['instagram'] ?? null,
            'website' => $validated['website'] ?? null,
            'profile_photo' => $validated['profile_photo'] ?? null,
            'primary_venue' => $validated['primary_venue'],
        ];
    }
}
