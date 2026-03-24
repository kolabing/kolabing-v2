<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateKolabRequest extends FormRequest
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
            // Always required
            'intent_type' => ['required', 'string', 'in:community_seeking,venue_promotion,product_promotion'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'preferred_city' => ['required', 'string', 'max:100'],

            // Community Seeking fields
            'needs' => ['required_if:intent_type,community_seeking', 'nullable', 'array'],
            'needs.*' => ['string', 'in:venue,food_drink,sponsor,products,discount,other'],
            'community_types' => ['required_if:intent_type,community_seeking', 'nullable', 'array'],
            'community_types.*' => ['string'],
            'community_size' => ['required_if:intent_type,community_seeking', 'nullable', 'integer', 'min:1'],
            'typical_attendance' => ['required_if:intent_type,community_seeking', 'nullable', 'integer', 'min:1'],
            'offers_in_return' => ['required_if:intent_type,community_seeking', 'nullable', 'array'],
            'offers_in_return.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],
            'venue_preference' => ['required_if:intent_type,community_seeking', 'nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion fields
            'venue_name' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'max:255'],
            'venue_type' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'in:restaurant,cafe,bar_lounge,hotel,coworking,sports_facility,event_space,rooftop,beach_club,retail_store,other'],
            'capacity' => ['required_if:intent_type,venue_promotion', 'nullable', 'integer', 'min:1'],
            'venue_address' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'max:500'],

            // Product Promotion fields
            'product_name' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'max:255'],
            'product_type' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'in:food_product,beverage,health_beauty,sports_equipment,fashion,tech_gadget,experience_service,other'],

            // Business Targeting fields (required unless community_seeking)
            'offering' => ['required_unless:intent_type,community_seeking', 'nullable', 'array'],
            'offering.*' => ['string', 'in:venue,food_drink,discount,products,social_media,content_creation,sponsorship,other'],

            // Optional fields
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media' => ['sometimes', 'nullable', 'array'],
            'media.*.url' => ['required_with:media', 'string', 'url'],
            'media.*.type' => ['required_with:media', 'string', 'in:photo,video'],
            'availability_mode' => ['sometimes', 'nullable', 'string', 'in:one_time,recurring,flexible'],
            'availability_start' => ['sometimes', 'nullable', 'date', 'after:today'],
            'availability_end' => ['sometimes', 'nullable', 'date', 'after:availability_start'],
            'selected_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'recurring_days' => ['sometimes', 'nullable', 'array'],
            'recurring_days.*' => ['integer', 'between:1,7'],
            'seeking_communities' => ['sometimes', 'nullable', 'array'],
            'seeking_communities.*' => ['string'],
            'min_community_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expects' => ['sometimes', 'nullable', 'array'],
            'expects.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],
            'past_events' => ['sometimes', 'nullable', 'array'],
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
            'preferred_city.required' => __('validation.required', ['attribute' => 'preferred city']),
            'preferred_city.max' => __('validation.max.string', ['attribute' => 'preferred city', 'max' => 100]),
            'needs.required_if' => __('validation.required_if', ['attribute' => 'needs', 'other' => 'intent type', 'value' => 'community_seeking']),
            'needs.*.in' => __('validation.in', ['attribute' => 'needs item']),
            'community_types.required_if' => __('validation.required_if', ['attribute' => 'community types', 'other' => 'intent type', 'value' => 'community_seeking']),
            'community_size.required_if' => __('validation.required_if', ['attribute' => 'community size', 'other' => 'intent type', 'value' => 'community_seeking']),
            'typical_attendance.required_if' => __('validation.required_if', ['attribute' => 'typical attendance', 'other' => 'intent type', 'value' => 'community_seeking']),
            'offers_in_return.required_if' => __('validation.required_if', ['attribute' => 'offers in return', 'other' => 'intent type', 'value' => 'community_seeking']),
            'offers_in_return.*.in' => __('validation.in', ['attribute' => 'offers in return item']),
            'venue_preference.required_if' => __('validation.required_if', ['attribute' => 'venue preference', 'other' => 'intent type', 'value' => 'community_seeking']),
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
