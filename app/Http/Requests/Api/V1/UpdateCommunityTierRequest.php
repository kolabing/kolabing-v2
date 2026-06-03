<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TierAssignmentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityTierRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:1', 'max:60'],
            'rank' => ['sometimes', 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'],
            'assignment_rule' => ['sometimes', 'string', Rule::in(TierAssignmentRule::values())],
            'threshold' => ['nullable', 'integer', 'min:0'],
            'permissions' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
