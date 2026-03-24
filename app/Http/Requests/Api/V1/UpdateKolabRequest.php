<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'preferred_city' => ['sometimes', 'string', 'max:100'],

            // Community Seeking fields
            'needs' => ['sometimes', 'nullable', 'array'],
            'needs.*' => ['string', 'in:venue,food_drink,sponsor,products,discount,other'],
            'community_types' => ['sometimes', 'nullable', 'array'],
            'community_types.*' => ['string'],
            'community_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'typical_attendance' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'offers_in_return' => ['sometimes', 'nullable', 'array'],
            'offers_in_return.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],
            'venue_preference' => ['sometimes', 'nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion fields
            'venue_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue_type' => ['sometimes', 'nullable', 'string', 'in:restaurant,cafe,bar_lounge,hotel,coworking,sports_facility,event_space,rooftop,beach_club,retail_store,other'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'venue_address' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Product Promotion fields
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_type' => ['sometimes', 'nullable', 'string', 'in:food_product,beverage,health_beauty,sports_equipment,fashion,tech_gadget,experience_service,other'],

            // Business Targeting fields
            'offering' => ['sometimes', 'nullable', 'array'],
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
            'intent_type.in' => __('validation.in', ['attribute' => 'intent type']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
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
}
