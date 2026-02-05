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
