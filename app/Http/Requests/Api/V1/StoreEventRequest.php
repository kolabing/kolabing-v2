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
     * Upcoming-event create mode is keyed on `starts_at`. Without it, the request
     * is the legacy retroactive past-showcase create (photos + date<=today).
     */
    public function isUpcoming(): bool
    {
        return $this->filled('starts_at');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if ($this->isUpcoming()) {
            return [
                'community_id' => ['required', 'uuid', 'exists:communities,id'],
                // The event's own city (where it happens). Drives city discover
                // independent of the host community's profile city. Nullable.
                'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
                'name' => ['required', 'string', 'min:3', 'max:100'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after:starts_at'],
                'location' => ['nullable', 'string', 'max:255'],
                'capacity' => ['nullable', 'integer', 'min:1'],
                // `tier` reuses tier_gate for the allowed tier ids.
                'tier_gate' => ['nullable', 'array', 'required_if:visibility,tier'],
                'tier_gate.*' => ['uuid'],
                // Visibility: public (surfaces in city discover) | members | tier.
                'visibility' => ['sometimes', Rule::enum(\App\Enums\EventVisibility::class)],
                'collaboration_id' => ['nullable', 'uuid', 'exists:collaborations,id'],
                'photos' => ['nullable', 'array', 'max:5'],
                'photos.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],

                // Optional recurrence → creates an event_series + materialises the
                // first window instead of a single event. Weekday: 0=Sun..6=Sat;
                // a list allows multi-day (e.g. [2,4] = Tue+Thu = twice weekly).
                'recurrence' => ['sometimes', 'array'],
                'recurrence.frequency' => ['required_with:recurrence', Rule::in(['weekly', 'biweekly', 'monthly'])],
                'recurrence.byweekday' => ['required_with:recurrence', 'array', 'min:1'],
                'recurrence.byweekday.*' => ['integer', 'between:0,6'],
                'recurrence.chat_mode' => ['sometimes', Rule::in(['per_event', 'series'])],
                'recurrence.ends_mode' => ['sometimes', Rule::in(['count', 'until', 'never'])],
                'recurrence.ends_count' => ['nullable', 'integer', 'min:1', 'max:200', 'required_if:recurrence.ends_mode,count'],
                'recurrence.ends_on' => ['nullable', 'date', 'after:starts_at', 'required_if:recurrence.ends_mode,until'],
            ];
        }

        // Legacy retroactive past-showcase event.
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'partner_name' => ['required', 'string', 'min:2', 'max:100'],
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
            'partner_name.required' => 'Partner name is required.',
            'partner_name.min' => 'Partner name must be at least 2 characters.',
            'partner_name.max' => 'Partner name cannot exceed 100 characters.',
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
