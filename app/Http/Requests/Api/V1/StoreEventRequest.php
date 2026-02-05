<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'partner_id' => ['required', 'uuid', 'exists:profiles,id'],
            'partner_type' => ['required', 'string', Rule::in([UserType::Business->value, UserType::Community->value])],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Event name is required.',
            'name.min' => 'Event name must be at least 3 characters.',
            'name.max' => 'Event name cannot exceed 100 characters.',
            'partner_id.required' => 'Partner is required.',
            'partner_id.exists' => 'The selected partner does not exist.',
            'partner_type.required' => 'Partner type is required.',
            'partner_type.in' => 'Partner type must be business or community.',
            'date.required' => 'Event date is required.',
            'date.before_or_equal' => 'Event date cannot be in the future.',
            'attendee_count.required' => 'Attendee count is required.',
            'attendee_count.min' => 'Attendee count must be at least 1.',
            'photos.required' => 'At least one photo is required.',
            'photos.max' => 'You can upload a maximum of 5 photos.',
            'photos.*.image' => 'Each photo must be an image file.',
            'photos.*.mimes' => 'Photos must be jpeg, jpg, png, gif, or webp.',
            'photos.*.max' => 'Each photo must not exceed 5MB.',
        ];
    }
}
