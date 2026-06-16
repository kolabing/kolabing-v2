<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\VenueType;
use App\Models\OfferOption;
use App\Support\OfferOptionValues;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as ValidationValidator;

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
            'name' => ['required', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:1000'],
            'business_type' => ['required_without:categories', 'nullable', 'string', 'in:'.implode(',', self::BUSINESS_TYPES)],
            'categories' => ['required_without:business_type', 'array', 'min:1', 'max:3'],
            'categories.*' => ['string', 'distinct', 'in:'.implode(',', self::BUSINESS_TYPES)],
            'has_venue' => ['required', 'boolean'],
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
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
