<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ChallengeDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChallengeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'difficulty' => ['sometimes', 'string', Rule::in(ChallengeDifficulty::values())],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Challenge name must be at least 3 characters.',
            'name.max' => 'Challenge name cannot exceed 150 characters.',
            'difficulty.in' => 'Difficulty must be easy, medium, or hard.',
            'points.min' => 'Points must be at least 1.',
            'points.max' => 'Points cannot exceed 100.',
        ];
    }
}
