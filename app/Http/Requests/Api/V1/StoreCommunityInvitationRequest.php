<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Accept a single `email` as a one-item `emails` list. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && ! $this->has('emails')) {
            $this->merge(['emails' => [$this->input('email')]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The panel pastes a list — the cheap half of bulk, without a CSV parser.
            'emails' => ['required', 'array', 'min:1', 'max:50'],
            'emails.*' => ['required', 'email', 'max:255'],
            'tier_id' => ['nullable', 'uuid', 'exists:community_tiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emails.required' => 'At least one email address is required.',
            'emails.max' => 'You can invite at most 50 people at a time.',
        ];
    }
}
