<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeProofType;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('target_value') === null || $this->input('target_value') === '') {
            $this->merge(['target_value' => 1]);
        }
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
            // Whether the app opens the camera when a pair agrees (#248).
            // Absent means unchanged on update and `text` on create, which is
            // what every challenge authored before #216 reports.
            'proof_type' => ['sometimes', Rule::in(ChallengeProofType::values())],
            'trigger_action' => ['nullable', Rule::in(MissionTrigger::values())],
            'target_value' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'repeat_interval' => ['nullable', Rule::in(MissionRepeat::values())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof_type.in' => 'How it is played must be text or photo.',
        ];
    }
}
