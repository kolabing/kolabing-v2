<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording a challenge with someone who does not have the app (#246).
 *
 * `ghost_name` is the only required detail, and that is deliberate. Asking a
 * stranger for their phone number at the moment you meet them is both bad
 * manners and a larger data-protection surface than this feature needs — the
 * contact is only ever a convenience for sending the invite.
 *
 * Whether the inviter is checked in, and whether they already hold too many
 * unclaimed invites, are the service's decisions: they are rules about the
 * meeting, not about the shape of the request, and they must hold for every
 * caller rather than just this route.
 */
class CreateGhostInviteRequest extends FormRequest
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
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'challenge_id' => ['required', 'uuid', 'exists:challenges,id'],
            'ghost_name' => ['required', 'string', 'min:1', 'max:80'],
            'ghost_contact' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ghost_name.required' => 'Enter their name so you both know who this is.',
            'ghost_name.max' => 'That name is too long.',
        ];
    }
}
