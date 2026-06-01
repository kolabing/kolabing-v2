<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChallengeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'difficulty' => ['required', Rule::in(ChallengeDifficulty::values())],
            'points' => ['required', 'integer', 'between:0,10000'],
            'category' => ['nullable', Rule::in(ChallengeCategory::values())],
            'audience' => ['required', Rule::in(ChallengeAudience::values())],
        ];
    }
}
