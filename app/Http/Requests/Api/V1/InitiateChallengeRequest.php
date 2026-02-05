<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitiateChallengeRequest extends FormRequest
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
            'challenge_id' => ['required', 'uuid', 'exists:challenges,id'],
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'verifier_profile_id' => ['required', 'uuid', 'exists:profiles,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $user = $this->user();
            if ($user && $this->input('verifier_profile_id') === $user->id) {
                $validator->errors()->add(
                    'verifier_profile_id',
                    'You cannot challenge yourself.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'challenge_id.required' => 'Challenge ID is required.',
            'challenge_id.uuid' => 'Challenge ID must be a valid UUID.',
            'challenge_id.exists' => 'The selected challenge does not exist.',
            'event_id.required' => 'Event ID is required.',
            'event_id.uuid' => 'Event ID must be a valid UUID.',
            'event_id.exists' => 'The selected event does not exist.',
            'verifier_profile_id.required' => 'Verifier profile ID is required.',
            'verifier_profile_id.uuid' => 'Verifier profile ID must be a valid UUID.',
            'verifier_profile_id.exists' => 'The selected verifier profile does not exist.',
        ];
    }
}
