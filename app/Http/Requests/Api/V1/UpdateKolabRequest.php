<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\IntentType;
use App\Models\OfferOption;
use App\Support\OfferOptionValues;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as ValidationValidator;

class UpdateKolabRequest extends FormRequest
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
            'intent_type' => ['sometimes', 'string', 'in:community_seeking,venue_promotion,product_promotion'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'offer_headline' => ['sometimes', 'nullable', 'string', 'max:50'],
            'base_offer' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'goal' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_GOAL))],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_KOLAB_HIGHLIGHT))],
            'negotiation_triggers' => ['sometimes', 'nullable', 'array'],
            'negotiation_triggers.*.condition' => ['required_with:negotiation_triggers', 'string', 'max:255'],
            'negotiation_triggers.*.additional_offer' => ['required_with:negotiation_triggers', 'string', 'max:1000'],
            'preferred_city' => ['sometimes', 'string', 'max:100'],

            // Community Seeking fields
            'needs' => ['sometimes', 'nullable', 'array'],
            'needs.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_NEED))],
            'community_types' => ['sometimes', 'nullable', 'array'],
            'community_types.*' => ['string'],
            'community_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'typical_attendance' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'offers_in_return' => ['sometimes', 'nullable', 'array'],
            'offers_in_return.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_DELIVERABLE))],
            'venue_preference' => ['sometimes', 'nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion fields
            'venue_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue_type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_VENUE_TYPE))],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'venue_address' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Product Promotion fields
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_PRODUCT_TYPE))],

            // Business Targeting fields
            'offering' => ['sometimes', 'nullable', 'array'],
            'offering.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_OFFERING))],

            // Optional fields
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media' => ['sometimes', 'nullable', 'array'],
            'media.*.url' => ['required_with:media', 'string', 'url'],
            'media.*.type' => ['required_with:media', 'string', 'in:image,video'],
            'media.*.thumbnail_url' => ['nullable', 'string', 'url'],
            'media.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'availability_mode' => ['sometimes', 'nullable', 'string', 'in:one_time,recurring,flexible,specific_dates'],
            'availability_start' => ['sometimes', 'nullable', 'date', 'after:today'],
            'availability_end' => ['sometimes', 'nullable', 'date', 'after:availability_start'],
            'selected_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'recurring_days' => ['sometimes', 'nullable', 'array'],
            'recurring_days.*' => ['integer', 'between:1,7'],
            'seeking_communities' => ['sometimes', 'nullable', 'array'],
            'seeking_communities.*' => ['string'],
            'min_community_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expects' => ['sometimes', 'nullable', 'array'],
            'expects.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_DELIVERABLE))],
            'past_events' => ['sometimes', 'nullable', 'array'],
            'past_events.*.name' => ['required_with:past_events', 'string', 'max:255'],
            'past_events.*.date' => ['required_with:past_events', 'date'],
            'past_events.*.partner_name' => ['nullable', 'string', 'max:255'],
            'past_events.*.photos' => ['nullable', 'array', 'max:3'],
            'past_events.*.photos.*' => ['string', 'url'],
            'past_events.*.media' => ['nullable', 'array', 'max:3'],
            'past_events.*.media.*.url' => ['required_with:past_events.*.media', 'string', 'url'],
            'past_events.*.media.*.type' => ['required_with:past_events.*.media', 'string', 'in:image,video'],
            'past_events.*.media.*.thumbnail_url' => ['nullable', 'string', 'url'],
            'past_events.*.media.*.sort_order' => ['nullable', 'integer', 'min:0'],
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
            'intent_type.in' => __('validation.in', ['attribute' => 'intent type']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
            'offer_headline.max' => __('validation.max.string', ['attribute' => 'offer headline', 'max' => 50]),
            'base_offer.max' => __('validation.max.string', ['attribute' => 'base offer', 'max' => 5000]),
            'goal.in' => __('validation.in', ['attribute' => 'goal']),
            'highlights.*.in' => __('validation.in', ['attribute' => 'highlights item']),
            'preferred_city.max' => __('validation.max.string', ['attribute' => 'preferred city', 'max' => 100]),
            'needs.*.in' => __('validation.in', ['attribute' => 'needs item']),
            'offers_in_return.*.in' => __('validation.in', ['attribute' => 'offers in return item']),
            'venue_preference.in' => __('validation.in', ['attribute' => 'venue preference']),
            'venue_name.max' => __('validation.max.string', ['attribute' => 'venue name', 'max' => 255]),
            'venue_type.in' => __('validation.in', ['attribute' => 'venue type']),
            'venue_address.max' => __('validation.max.string', ['attribute' => 'venue address', 'max' => 500]),
            'product_name.max' => __('validation.max.string', ['attribute' => 'product name', 'max' => 255]),
            'product_type.in' => __('validation.in', ['attribute' => 'product type']),
            'offering.*.in' => __('validation.in', ['attribute' => 'offering item']),
            'availability_mode.in' => __('validation.in', ['attribute' => 'availability mode']),
            'availability_start.after' => __('validation.after', ['attribute' => 'availability start', 'date' => 'today']),
            'availability_end.after' => __('validation.after', ['attribute' => 'availability end', 'date' => 'availability start']),
            'selected_time.date_format' => __('validation.date_format', ['attribute' => 'selected time', 'format' => 'HH:mm']),
            'recurring_days.*.between' => __('validation.between.numeric', ['attribute' => 'recurring day', 'min' => 1, 'max' => 7]),
            'expects.*.in' => __('validation.in', ['attribute' => 'expects item']),
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

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $profile = $this->user();
            $intentType = $this->input('intent_type');
            $kolab = $this->route('kolab');

            if ($profile?->isCommunity() && in_array($intentType, [
                IntentType::VenuePromotion->value,
                IntentType::ProductPromotion->value,
            ], true)) {
                $validator->errors()->add(
                    'intent_type',
                    __('Community accounts can only update kolabs within the community flow.')
                );
            }

            $isVenuePromotion = $intentType === IntentType::VenuePromotion->value
                || ($intentType === null && $kolab?->intent_type?->value === IntentType::VenuePromotion->value);

            if (! $isVenuePromotion) {
                return;
            }

            if (! $profile?->isBusiness()) {
                return;
            }

            $profile->loadMissing('businessProfile');

            if (empty($profile->businessProfile?->primary_venue)) {
                $validator->errors()->add(
                    'primary_venue',
                    __('A primary venue profile is required before updating a venue promotion kolab.')
                );
            }
        });
    }
}
