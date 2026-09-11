<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A community's whole challenge checklist, in one request (kolabing-app#150).
 *
 * A sync rather than add/remove one at a time, because the screen is a checklist
 * and the request should be the same shape as the thing the leader is looking at.
 *
 * **An empty array is allowed**, and that is the one thing to notice here: it
 * means "no curation", which returns the community to the whole library and is
 * the only way back. `SyncCollaborationChallengesRequest` — the existing
 * precedent for this shape — requires `min:1`; this deliberately does not,
 * because there the empty case is a mistake and here it is a choice.
 */
class SyncCommunityChallengesRequest extends FormRequest
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
            'challenges' => ['present', 'array'],
            'challenges.*.challenge_id' => ['required', 'uuid', 'exists:challenges,id'],
            'challenges.*.allow_repeat_with_same_person' => ['sometimes', 'boolean'],
            'challenges.*.requires_new_person' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'challenges.present' => 'Send a challenges array — an empty one turns curation off.',
            'challenges.*.challenge_id.exists' => 'One or more challenges do not exist.',
        ];
    }
}
