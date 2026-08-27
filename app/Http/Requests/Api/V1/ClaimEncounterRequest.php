<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Redeeming an invite code (#246).
 *
 * Reached from two doors for one code: the deep-link handler when the app was
 * already installed, and the onboarding field when it was not. Only length and
 * shape are checked here — whether the code exists, is still open, and belongs
 * to a genuinely new account are the service's decisions.
 */
class ClaimEncounterRequest extends FormRequest
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
            // Trimmed and upper-cased by the service, so a code pasted with a
            // stray space or typed in lower case still works. Someone is
            // copying this off a screen.
            'claim_code' => ['required', 'string', 'min:4', 'max:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'claim_code.required' => 'Enter the invite code.',
        ];
    }
}
