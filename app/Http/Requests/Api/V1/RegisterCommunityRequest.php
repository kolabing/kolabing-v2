<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterCommunityRequest extends FormRequest
{
    /**
     * Valid community types.
     *
     * @var array<string>
     */
    public const COMMUNITY_TYPES = [
        'food_blogger',
        'lifestyle_influencer',
        'fitness_enthusiast',
        'travel_blogger',
        'photographer',
        'local_explorer',
        'student',
        'professional',
        'community_organizer',
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
            'community_type' => ['required', 'string', 'in:'.implode(',', self::COMMUNITY_TYPES)],
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'instagram' => ['nullable', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9._]+$/'],
            'tiktok' => ['nullable', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9._]+$/'],
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
            'email.required' => __('The email field is required'),
            'email.email' => __('The email must be a valid email address'),
            'email.unique' => __('This email is already registered'),
            'password.required' => __('The password field is required'),
            'password.min' => __('The password must be at least 8 characters'),
            'password.confirmed' => __('The password confirmation does not match'),
            'name.required' => __('The name field is required'),
            'name.max' => __('The name must not exceed 255 characters'),
            'about.max' => __('The about description must not exceed 1000 characters'),
            'community_type.required' => __('The community type field is required'),
            'community_type.in' => __('The selected community type is invalid'),
            'city_id.required' => __('The city field is required'),
            'city_id.uuid' => __('The city ID must be a valid UUID'),
            'city_id.exists' => __('The selected city does not exist'),
            'phone_number.regex' => __('The phone number format is invalid. Use E.164 format (e.g., +34612345678)'),
            'instagram.regex' => __('The instagram handle format is invalid'),
            'tiktok.regex' => __('The tiktok handle format is invalid'),
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
     * Get the validated community profile data.
     *
     * @return array{
     *     name: string,
     *     about: string|null,
     *     community_type: string,
     *     city_id: string,
     *     instagram: string|null,
     *     tiktok: string|null,
     *     website: string|null,
     *     profile_photo: string|null
     * }
     */
    public function getCommunityProfileData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'about' => $validated['about'] ?? null,
            'community_type' => $validated['community_type'],
            'city_id' => $validated['city_id'],
            'instagram' => $validated['instagram'] ?? null,
            'tiktok' => $validated['tiktok'] ?? null,
            'website' => $validated['website'] ?? null,
            'profile_photo' => $validated['profile_photo'] ?? null,
        ];
    }
}
