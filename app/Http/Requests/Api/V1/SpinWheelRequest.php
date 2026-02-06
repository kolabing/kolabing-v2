<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SpinWheelRequest extends FormRequest
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
            'challenge_completion_id' => ['required', 'uuid', 'exists:challenge_completions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'challenge_completion_id.required' => 'Challenge completion ID is required.',
            'challenge_completion_id.uuid' => 'Challenge completion ID must be a valid UUID.',
            'challenge_completion_id.exists' => 'The selected challenge completion does not exist.',
        ];
    }
}
