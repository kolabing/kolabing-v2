<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityBadgeCriteriaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityBadgeRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:120'],
            'icon' => ['nullable', 'string', 'max:80'],
            'criteria_type' => ['required', 'string', Rule::in(CommunityBadgeCriteriaType::values())],
            'criteria_value' => ['required', 'integer', 'min:1'],
            'challenge_ids' => [
                'nullable',
                'array',
                Rule::requiredIf(fn (): bool => $this->input('criteria_type') === CommunityBadgeCriteriaType::ChallengesCompleted->value),
            ],
            'challenge_ids.*' => ['uuid', 'exists:challenges,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
