<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\IntentType;
use App\Models\OfferOption;
use App\Support\OfferOptionValues;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class CreateKolabRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('intent_type') !== IntentType::CommunitySeeking->value) {
            return;
        }

        $venuePreference = $this->input('venue_preference');

        if (is_string($venuePreference) && $venuePreference !== '') {
            return;
        }

        $this->merge([
            'venue_preference' => 'no_venue',
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
            // Always required
            'intent_type' => ['required', 'string', 'in:community_seeking,venue_promotion,product_promotion'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'offer_headline' => ['sometimes', 'nullable', 'string', 'max:50'],
            'base_offer' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'goal' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_GOAL))],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_KOLAB_HIGHLIGHT))],
            'negotiation_triggers' => ['sometimes', 'nullable', 'array'],
            'negotiation_triggers.*.condition' => ['required_with:negotiation_triggers', 'string', 'max:255'],
            'negotiation_triggers.*.additional_offer' => ['required_with:negotiation_triggers', 'string', 'max:1000'],
            'preferred_city' => ['required_unless:intent_type,venue_promotion', 'nullable', 'string', 'max:100'],

            /*
             * The end of the suggestion funnel (BE-NF-39). Optional and additive:
             * every existing client keeps working, and a client that sends it
             * gets `converted_kolab_id` written on the row (KolabService::create).
             *
             * The `exists` rule is scoped to the caller's own suggestions on
             * purpose. Ownership checked *here* turns a stranger's id into a
             * clean 422; ownership checked only afterwards would make it a silent
             * no-op that quietly marks someone else's row converted and
             * corrupts the one funnel this feature is judged on. A null viewer
             * (unreachable behind auth) scopes to `viewer_profile_id is null`,
             * which the NOT NULL column can never match — it fails closed.
             *
             * Liveness is deliberately NOT required: an expired or dismissed row
             * of the caller's own still converts, because refusing the create
             * would let a stale card block Kolab creation.
             */
            'suggestion_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('kolab_suggestions', 'id')->where('viewer_profile_id', $this->user()?->id),
            ],

            // Community Seeking fields
            'needs' => ['required_if:intent_type,community_seeking', 'nullable', 'array'],
            'needs.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_NEED))],
            // community_types + community_size are inherited from the creator's
            // community_profile (KolabService::enrichCommunitySeekingData), so
            // they are NOT required here — the app no longer asks for them.
            'community_types' => ['nullable', 'array'],
            'community_types.*' => ['string'],
            'community_size' => ['nullable', 'integer', 'min:1'],
            'typical_attendance' => ['required_if:intent_type,community_seeking', 'nullable', 'integer', 'min:1'],
            'offers_in_return' => ['required_if:intent_type,community_seeking', 'nullable', 'array'],
            'offers_in_return.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_DELIVERABLE))],
            'venue_preference' => ['nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion fields
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_type' => ['nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_VENUE_TYPE))],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'venue_address' => ['nullable', 'string', 'max:500'],

            // Product Promotion fields
            'product_name' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'max:255'],
            'product_type' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_PRODUCT_TYPE))],

            // Business Targeting fields (required unless community_seeking)
            'offering' => ['required_unless:intent_type,community_seeking', 'nullable', 'array'],
            'offering.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_OFFERING))],

            // Optional fields
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media' => ['required_if:intent_type,venue_promotion', 'nullable', 'array', 'min:1'],
            'media.*.url' => ['required_with:media', 'string', 'url'],
            'media.*.type' => ['required_with:media', 'string', 'in:image,video'],
            'media.*.thumbnail_url' => ['nullable', 'string', 'url'],
            'media.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'availability_mode' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'in:one_time,recurring,flexible,specific_dates,immediate'],
            'availability_start' => [
                'required_if:intent_type,venue_promotion',
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $isImmediate = $this->input('availability_mode') === 'immediate';
                    $minDate = $isImmediate ? today() : today()->addDay();

                    if (\Illuminate\Support\Carbon::parse($value)->lt($minDate)) {
                        $fail($isImmediate
                            ? __('The availability start date cannot be in the past.')
                            : __('validation.after', ['attribute' => 'availability start', 'date' => 'today']));
                    }
                },
            ],
            'availability_end' => ['nullable', 'date', 'after:availability_start'],
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
            'intent_type.required' => __('validation.required', ['attribute' => 'intent type']),
            'intent_type.in' => __('validation.in', ['attribute' => 'intent type']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.required' => __('validation.required', ['attribute' => 'description']),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
            'offer_headline.max' => __('validation.max.string', ['attribute' => 'offer headline', 'max' => 50]),
            'base_offer.max' => __('validation.max.string', ['attribute' => 'base offer', 'max' => 5000]),
            'goal.in' => __('validation.in', ['attribute' => 'goal']),
            'highlights.*.in' => __('validation.in', ['attribute' => 'highlights item']),
            'preferred_city.required_unless' => __('validation.required_unless', ['attribute' => 'preferred city', 'other' => 'intent type', 'values' => 'venue_promotion']),
            'preferred_city.max' => __('validation.max.string', ['attribute' => 'preferred city', 'max' => 100]),
            'needs.required_if' => __('validation.required_if', ['attribute' => 'needs', 'other' => 'intent type', 'value' => 'community_seeking']),
            'needs.*.in' => __('validation.in', ['attribute' => 'needs item']),
            'community_types.required_if' => __('validation.required_if', ['attribute' => 'community types', 'other' => 'intent type', 'value' => 'community_seeking']),
            'community_size.required_if' => __('validation.required_if', ['attribute' => 'community size', 'other' => 'intent type', 'value' => 'community_seeking']),
            'typical_attendance.required_if' => __('validation.required_if', ['attribute' => 'typical attendance', 'other' => 'intent type', 'value' => 'community_seeking']),
            'offers_in_return.required_if' => __('validation.required_if', ['attribute' => 'offers in return', 'other' => 'intent type', 'value' => 'community_seeking']),
            'offers_in_return.*.in' => __('validation.in', ['attribute' => 'offers in return item']),
            'venue_preference.in' => __('validation.in', ['attribute' => 'venue preference']),
            'venue_name.required_if' => __('validation.required_if', ['attribute' => 'venue name', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'venue_type.required_if' => __('validation.required_if', ['attribute' => 'venue type', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'venue_type.in' => __('validation.in', ['attribute' => 'venue type']),
            'capacity.required_if' => __('validation.required_if', ['attribute' => 'capacity', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'venue_address.required_if' => __('validation.required_if', ['attribute' => 'venue address', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'product_name.required_if' => __('validation.required_if', ['attribute' => 'product name', 'other' => 'intent type', 'value' => 'product_promotion']),
            'product_type.required_if' => __('validation.required_if', ['attribute' => 'product type', 'other' => 'intent type', 'value' => 'product_promotion']),
            'product_type.in' => __('validation.in', ['attribute' => 'product type']),
            'offering.required_unless' => __('validation.required_unless', ['attribute' => 'offering', 'other' => 'intent type', 'values' => 'community_seeking']),
            'offering.*.in' => __('validation.in', ['attribute' => 'offering item']),
            'availability_mode.in' => __('validation.in', ['attribute' => 'availability mode']),
            'availability_start.after' => __('validation.after', ['attribute' => 'availability start', 'date' => 'today']),
            'availability_end.after' => __('validation.after', ['attribute' => 'availability end', 'date' => 'availability start']),
            'selected_time.date_format' => __('validation.date_format', ['attribute' => 'selected time', 'format' => 'HH:mm']),
            'recurring_days.*.between' => __('validation.between.numeric', ['attribute' => 'recurring day', 'min' => 1, 'max' => 7]),
            'expects.*.in' => __('validation.in', ['attribute' => 'expects item']),
            'suggestion_id.uuid' => __('validation.uuid', ['attribute' => 'suggestion']),
            'suggestion_id.exists' => __('validation.exists', ['attribute' => 'suggestion']),
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

            if ($profile?->isCommunity() && in_array($intentType, [
                IntentType::VenuePromotion->value,
                IntentType::ProductPromotion->value,
            ], true)) {
                $validator->errors()->add(
                    'intent_type',
                    __('Community accounts can only create community-seeking kolabs.')
                );
            }

            if ($intentType !== IntentType::VenuePromotion->value) {
                return;
            }

            if (! $profile?->isBusiness()) {
                return;
            }

            $profile->loadMissing('businessProfile');

            if (empty($profile->businessProfile?->primary_venue)) {
                $validator->errors()->add(
                    'primary_venue',
                    __('A primary venue profile is required before creating a venue promotion kolab.')
                );
            }
        });
    }
}
