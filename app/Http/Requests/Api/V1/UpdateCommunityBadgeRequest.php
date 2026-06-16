<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityBadgeCriteriaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityBadgeRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'min:1', 'max:120'],
            'icon' => ['nullable', 'string', 'max:80'],
            'criteria_type' => ['sometimes', 'string', Rule::in(CommunityBadgeCriteriaType::values())],
            'criteria_value' => ['sometimes', 'integer', 'min:1'],
            'challenge_ids' => ['nullable', 'array'],
            'challenge_ids.*' => ['uuid', 'exists:challenges,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
