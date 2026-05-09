<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\IntentType;
use App\Enums\VenueType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DiscoverOpportunityRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private const OFFER_TYPES = [
        'venue',
        'food_drink',
        'discount',
        'products',
        'social_media',
        'content_creation',
        'sponsorship',
        'other',
    ];

    /**
     * @var array<int, string>
     */
    private const DELIVERABLE_TYPES = [
        'social_media',
        'event_activation',
        'product_placement',
        'community_reach',
        'review_feedback',
    ];

    /**
     * @var array<int, string>
     */
    private const NEED_TYPES = [
        'venue',
        'food_drink',
        'sponsor',
        'products',
        'discount',
        'other',
    ];

    /**
     * @var array<int, string>
     */
    private const PRODUCT_TYPES = [
        'food_product',
        'beverage',
        'health_beauty',
        'sports_equipment',
        'fashion',
        'tech_gadget',
        'experience_service',
        'other',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $feed = $this->input('feed');

        $this->merge([
            'feed' => is_string($feed) && $feed !== '' ? $feed : 'recommended',
            'page' => (int) $this->input('page', 1),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'feed' => ['required', 'string', 'in:recommended,all'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'availability_mode' => ['sometimes', 'nullable', 'string', 'in:one_time,recurring,flexible,specific_dates'],
            'availability_from' => ['sometimes', 'nullable', 'date'],
            'availability_to' => ['sometimes', 'nullable', 'date'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:recommended,recent,ending_soon'],

            'need_types' => ['sometimes', 'array'],
            'need_types.*' => ['string', 'in:'.implode(',', self::NEED_TYPES)],
            'community_types' => ['sometimes', 'array'],
            'community_types.*' => ['string', 'max:100'],
            'audience_size_band' => ['sometimes', 'nullable', 'string', 'in:small,medium,large,xlarge'],
            'offers_in_return' => ['sometimes', 'array'],
            'offers_in_return.*' => ['string', 'in:'.implode(',', self::DELIVERABLE_TYPES)],
            'venue_preferences' => ['sometimes', 'array'],
            'venue_preferences.*' => ['string', 'in:business_provides,community_provides,no_venue'],

            'intent_types' => ['sometimes', 'array'],
            'intent_types.*' => ['string', 'in:'.implode(',', IntentType::values())],
            'offer_types' => ['sometimes', 'array'],
            'offer_types.*' => ['string', 'in:'.implode(',', self::OFFER_TYPES)],
            'venue_types' => ['sometimes', 'array'],
            'venue_types.*' => ['string', 'in:'.implode(',', VenueType::values())],
            'product_types' => ['sometimes', 'array'],
            'product_types.*' => ['string', 'in:'.implode(',', self::PRODUCT_TYPES)],
            'expected_deliverables' => ['sometimes', 'array'],
            'expected_deliverables.*' => ['string', 'in:'.implode(',', self::DELIVERABLE_TYPES)],
            'community_requirement_band' => ['sometimes', 'nullable', 'string', 'in:open,small,medium,large,xlarge'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'feed.in' => __('The selected feed is invalid.'),
            'page.min' => __('The page must be at least 1.'),
            'per_page.max' => __('The per page value may not be greater than 100.'),
            'sort.in' => __('The selected sort is invalid.'),
        ];
    }

    /**
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
