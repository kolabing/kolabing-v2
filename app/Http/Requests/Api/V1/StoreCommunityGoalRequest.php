<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityGoalEarnType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityGoalRequest extends FormRequest
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
            'earn_type' => ['required', 'string', Rule::in(CommunityGoalEarnType::values())],
            'target' => ['required', 'integer', 'min:1'],
            'reward_points' => ['required', 'integer', 'min:0'],
            'challenge_id' => [
                'nullable',
                'uuid',
                'exists:challenges,id',
                Rule::requiredIf(fn (): bool => $this->input('earn_type') === CommunityGoalEarnType::Challenge->value),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
