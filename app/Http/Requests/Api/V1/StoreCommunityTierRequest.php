<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TierAssignmentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityTierRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'rank' => ['required', 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'],
            'assignment_rule' => ['required', 'string', Rule::in(TierAssignmentRule::values())],
            'threshold' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn (): bool => ($this->input('assignment_rule')) !== TierAssignmentRule::Manual->value),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.view' => ['sometimes', 'array'],
            'permissions.chat_channels' => ['sometimes', 'array'],
            'permissions.perks' => ['sometimes', 'array'],
            'permissions.capabilities' => ['sometimes', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'threshold.required' => 'A threshold is required for non-manual assignment rules.',
            'color.regex' => 'The color must be a valid hex code (e.g. #FFD861).',
            'assignment_rule.in' => 'The assignment rule is not valid.',
        ];
    }
}
