<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOpportunityRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'business_offer' => ['required', 'array'],
            'community_deliverables' => ['required', 'array'],
            'categories' => ['required', 'array', 'min:1', 'max:5'],
            'categories.*' => ['string'],
            'availability_mode' => ['required', 'string', 'in:one_time,recurring,flexible'],
            'availability_start' => ['required', 'date', 'after:today'],
            'availability_end' => ['required', 'date', 'after:availability_start'],
            'venue_mode' => ['required', 'string', 'in:business_venue,community_venue,no_venue'],
            'address' => ['required_unless:venue_mode,no_venue', 'nullable', 'string'],
            'preferred_city' => ['required', 'string', 'max:100'],
            'offer_photo' => ['nullable', 'string', 'url'],
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
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.required' => __('validation.required', ['attribute' => 'description']),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
            'business_offer.required' => __('validation.required', ['attribute' => 'business offer']),
            'business_offer.array' => __('validation.array', ['attribute' => 'business offer']),
            'community_deliverables.required' => __('validation.required', ['attribute' => 'community deliverables']),
            'community_deliverables.array' => __('validation.array', ['attribute' => 'community deliverables']),
            'categories.required' => __('validation.required', ['attribute' => 'categories']),
            'categories.min' => __('validation.min.array', ['attribute' => 'categories', 'min' => 1]),
            'categories.max' => __('validation.max.array', ['attribute' => 'categories', 'max' => 5]),
            'availability_mode.required' => __('validation.required', ['attribute' => 'availability mode']),
            'availability_mode.in' => __('validation.in', ['attribute' => 'availability mode']),
            'availability_start.required' => __('validation.required', ['attribute' => 'availability start']),
            'availability_start.after' => __('validation.after', ['attribute' => 'availability start', 'date' => 'today']),
            'availability_end.required' => __('validation.required', ['attribute' => 'availability end']),
            'availability_end.after' => __('validation.after', ['attribute' => 'availability end', 'date' => 'availability start']),
            'venue_mode.required' => __('validation.required', ['attribute' => 'venue mode']),
            'venue_mode.in' => __('validation.in', ['attribute' => 'venue mode']),
            'address.required_unless' => __('validation.required_unless', ['attribute' => 'address', 'other' => 'venue mode', 'values' => 'no_venue']),
            'preferred_city.required' => __('validation.required', ['attribute' => 'preferred city']),
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
