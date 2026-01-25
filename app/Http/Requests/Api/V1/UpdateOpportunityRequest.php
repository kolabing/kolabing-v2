<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOpportunityRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'business_offer' => ['sometimes', 'array'],
            'community_deliverables' => ['sometimes', 'array'],
            'categories' => ['sometimes', 'array', 'min:1', 'max:5'],
            'categories.*' => ['string'],
            'availability_mode' => ['sometimes', 'string', 'in:one_time,recurring,flexible'],
            'availability_start' => ['sometimes', 'date', 'after:today'],
            'availability_end' => ['sometimes', 'date', 'after:availability_start'],
            'venue_mode' => ['sometimes', 'string', 'in:business_venue,community_venue,no_venue'],
            'address' => ['sometimes', 'nullable', 'string'],
            'preferred_city' => ['sometimes', 'string', 'max:100'],
            'offer_photo' => ['sometimes', 'nullable', 'string', 'url'],
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
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
            'business_offer.array' => __('validation.array', ['attribute' => 'business offer']),
            'community_deliverables.array' => __('validation.array', ['attribute' => 'community deliverables']),
            'categories.min' => __('validation.min.array', ['attribute' => 'categories', 'min' => 1]),
            'categories.max' => __('validation.max.array', ['attribute' => 'categories', 'max' => 5]),
            'availability_mode.in' => __('validation.in', ['attribute' => 'availability mode']),
            'availability_start.after' => __('validation.after', ['attribute' => 'availability start', 'date' => 'today']),
            'availability_end.after' => __('validation.after', ['attribute' => 'availability end', 'date' => 'availability start']),
            'venue_mode.in' => __('validation.in', ['attribute' => 'venue mode']),
            'preferred_city.max' => __('validation.max.string', ['attribute' => 'preferred city', 'max' => 100]),
            'offer_photo.url' => __('validation.url', ['attribute' => 'offer photo']),
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
