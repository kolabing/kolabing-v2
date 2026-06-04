<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the email before validation so the lookup is an exact match.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Invite by the email on a Kolabing account (preferred); profile_id kept
            // for backward-compat. Exactly one is required.
            'email' => ['required_without:profile_id', 'email'],
            'profile_id' => ['required_without:email', 'uuid', 'exists:profiles,id'],
            'tier_id' => ['nullable', 'uuid', 'exists:community_tiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'An email or profile_id is required to add a member.',
            'profile_id.required_without' => 'An email or profile_id is required to add a member.',
            'profile_id.exists' => 'No profile exists for the given profile_id.',
        ];
    }
}
