<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityGoalEarnType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityGoalRequest extends FormRequest
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
            'earn_type' => ['sometimes', 'string', Rule::in(CommunityGoalEarnType::values())],
            'target' => ['sometimes', 'integer', 'min:1'],
            'reward_points' => ['sometimes', 'integer', 'min:0'],
            'challenge_id' => ['nullable', 'uuid', 'exists:challenges,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
