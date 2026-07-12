<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\VenueType;
use App\Models\OfferOption;
use App\Support\ApiDebugLogger;
use App\Support\OfferOptionValues;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as ValidationValidator;

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

        // Goal flag. Absent => legacy venue path (backward compatible).
        $this->merge([
            'has_venue' => $this->has('has_venue') ? $this->boolean('has_venue') : true,
        ]);
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
            'accepted_terms' => ['required', 'accepted'],
            'name' => ['required', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:1000'],
            'business_type' => ['required_without:categories', 'nullable', 'string', 'exists:business_types,slug'],
            'categories' => ['required_without:business_type', 'array', 'min:1', 'max:3'],
            'categories.*' => ['string', 'distinct', 'exists:business_types,slug'],
            'has_venue' => ['required', 'boolean'],
            // Venue path requires a city via id or name; product path always
            // needs a concrete city_id (derived app-side from the first target).
            'city_id' => ['nullable', 'uuid', 'exists:cities,id', 'required_without:city_name', 'required_if:has_venue,false'],
            'city_name' => ['nullable', 'string', 'max:100', 'required_without:city_id'],
            'target_city_ids' => ['nullable', 'array'],
            'target_city_ids.*' => ['uuid', 'distinct', 'exists:cities,id'],
            'offering' => ['nullable', 'string', 'max:2000'],
            // Product path: optional product_type from the admin-managed taxonomy
            // (offer_options kind=product_type). Defaults to 'other'; the auto-offer
            // persists + reuses it.
            'product_type' => ['nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_PRODUCT_TYPE))],
            'offer_photos' => ['nullable', 'array'],
            'offer_photos.*' => ['string'],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'instagram' => ['nullable', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9._]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'string'],
            // Venue is required only on the venue path (has_venue = true).
            'primary_venue' => ['required_if:has_venue,true', 'nullable', 'array'],
            'primary_venue.name' => ['required_with:primary_venue', 'string', 'max:255'],
            'primary_venue.venue_type' => ['required_with:primary_venue', 'string', 'in:'.implode(',', VenueType::values())],
            'primary_venue.capacity' => ['required_with:primary_venue', 'integer', 'min:1'],
            'primary_venue.place_id' => ['nullable', 'string', 'max:255'],
            'primary_venue.formatted_address' => ['required_with:primary_venue', 'string', 'max:500'],
            'primary_venue.city' => ['required_with:primary_venue', 'string', 'max:100'],
            'primary_venue.country' => ['nullable', 'string', 'max:100'],
            'primary_venue.latitude' => ['nullable', 'numeric'],
            'primary_venue.longitude' => ['nullable', 'numeric'],
            'primary_venue.phone_number' => ['nullable', 'string', 'max:30'],
            'primary_venue.website' => ['nullable', 'url', 'max:255'],
            'primary_venue.opening_hours' => ['nullable', 'array'],
            'primary_venue.opening_hours.*' => ['string', 'max:255'],
            'primary_venue.description' => ['nullable', 'string', 'max:1000'],
            'primary_venue.price_level' => ['nullable', 'string', 'max:50'],
            'primary_venue.rating' => ['nullable', 'numeric', 'between:0,5'],
            'primary_venue.user_ratings_total' => ['nullable', 'integer', 'min:0'],
            'primary_venue.google_place_types' => ['nullable', 'array'],
            'primary_venue.google_place_types.*' => ['string', 'max:100'],
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
            'accepted_terms.required' => __('You must accept the Terms of Service and Privacy Policy'),
            'accepted_terms.accepted' => __('You must accept the Terms of Service and Privacy Policy'),
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
            'city_id.required_if' => __('The city field is required'),
            'city_id.uuid' => __('The city ID must be a valid UUID'),
            'city_id.exists' => __('The selected city does not exist'),
            'city_name.required_without' => __('The city field is required'),
            'target_city_ids.*.exists' => __('The selected city does not exist'),
            'primary_venue.required_if' => __('The venue details are required'),
            'phone_number.regex' => __('The phone number format is invalid. Use E.164 format (e.g., +34612345678)'),
            'instagram.regex' => __('The instagram handle format is invalid'),
            'website.url' => __('The website must be a valid URL'),
            'primary_venue.website.url' => __('The venue website must be a valid URL'),
            'primary_venue.photos.*.url' => __('The venue photo must be a valid URL'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $this->validatePhotoList($validator, 'primary_venue.photos');
            $this->validatePhotoList($validator, 'offer_photos');
        });
    }

    /**
     * Validate that each entry in a photo array is a URL, Google Places photo
     * resource name, or base64 image payload.
     */
    private function validatePhotoList(ValidationValidator $validator, string $key): void
    {
        $photos = $this->input($key, []);

        if (! is_array($photos)) {
            return;
        }

        foreach ($photos as $index => $photo) {
            if (! is_string($photo) || $photo === '') {
                $validator->errors()->add("{$key}.{$index}", __('The venue photo must be a valid URL'));

                continue;
            }

            if (
                filter_var($photo, FILTER_VALIDATE_URL)
                || preg_match('#^places/[^/]+/photos/[^/]+$#', $photo) === 1
                || preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $photo) === 1
                || base64_decode($photo, true) !== false
            ) {
                continue;
            }

            $validator->errors()->add("{$key}.{$index}", __('The venue photo must be a valid URL'));
        }
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        ApiDebugLogger::logValidationFailure($this, $validator->errors()->toArray());

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
     *     business_type: string|null,
     *     categories: array<int, string>,
     *     has_venue: bool,
     *     city_id: string|null,
     *     city_name: string|null,
     *     target_city_ids: array<int, string>,
     *     offering: string|null,
     *     product_type: string,
     *     offer_photos: array<int, string>,
     *     instagram: string|null,
     *     website: string|null,
     *     profile_photo: string|null,
     *     primary_venue: array<string, mixed>|null
     * }
     */
    public function getBusinessProfileData(): array
    {
        $validated = $this->validated();
        $categories = $this->normalizeCategories($validated);

        return [
            'name' => $validated['name'],
            'about' => $validated['about'] ?? null,
            'business_type' => $categories[0] ?? null,
            'categories' => $categories,
            'has_venue' => (bool) ($validated['has_venue'] ?? true),
            'city_id' => $validated['city_id'] ?? null,
            'city_name' => $validated['city_name'] ?? null,
            'target_city_ids' => array_values($validated['target_city_ids'] ?? []),
            'offering' => $validated['offering'] ?? null,
            'product_type' => $validated['product_type'] ?? 'other',
            'offer_photos' => array_values($validated['offer_photos'] ?? []),
            'instagram' => $validated['instagram'] ?? null,
            'website' => $validated['website'] ?? null,
            'profile_photo' => $validated['profile_photo'] ?? null,
            'primary_venue' => $validated['primary_venue'] ?? null,
        ];
    }

    /**
     * Normalize the ordered business categories.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, string>
     */
    private function normalizeCategories(array $validated): array
    {
        $categories = $validated['categories'] ?? [];

        if (! is_array($categories) || $categories === []) {
            $businessType = $validated['business_type'] ?? null;

            return is_string($businessType) && $businessType !== ''
                ? [$businessType]
                : [];
        }

        return array_values($categories);
    }
}
