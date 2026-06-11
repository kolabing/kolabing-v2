<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'partner_name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'partner_type' => ['sometimes', 'string', Rule::in([UserType::Business->value, UserType::Community->value])],
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
            'attendee_count' => ['sometimes', 'integer', 'min:1'],
            // Upcoming-event fields (NF-16 edit). future allowed.
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tier_gate' => ['sometimes', 'nullable', 'array'],
            'tier_gate.*' => ['uuid'],
            // Visibility: public (surfaces in city discover) | members | tier.
            'visibility' => ['sometimes', Rule::enum(\App\Enums\EventVisibility::class)],

            // Edit → make recurring: a recurrence block on a non-series event
            // converts it into a series (this event becomes occurrence 0).
            'recurrence' => ['sometimes', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::in(['weekly', 'biweekly', 'monthly'])],
            'recurrence.byweekday' => ['required_with:recurrence', 'array', 'min:1'],
            'recurrence.byweekday.*' => ['integer', 'between:0,6'],
            'recurrence.chat_mode' => ['sometimes', Rule::in(['per_event', 'series'])],
            'recurrence.ends_mode' => ['sometimes', Rule::in(['count', 'until', 'never'])],
            'recurrence.ends_count' => ['nullable', 'integer', 'min:1', 'max:200', 'required_if:recurrence.ends_mode,count'],
            'recurrence.ends_on' => ['nullable', 'date', 'required_if:recurrence.ends_mode,until'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Event name must be at least 3 characters.',
            'name.max' => 'Event name cannot exceed 100 characters.',
            'partner_name.min' => 'Partner name must be at least 2 characters.',
            'partner_name.max' => 'Partner name cannot exceed 100 characters.',
            'partner_type.in' => 'Partner type must be business or community.',
            'date.before_or_equal' => 'Event date cannot be in the future.',
            'attendee_count.min' => 'Attendee count must be at least 1.',
        ];
    }
}
