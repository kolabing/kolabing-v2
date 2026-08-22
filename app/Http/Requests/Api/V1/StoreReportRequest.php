<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'target_type' => ['required', 'string', Rule::in([
                'profile', 'kolab', 'review', 'chat_message',
                // Multi-Kolab Event MVP (per the founder's moderation
                // decision, contract §12): reactive report-only, no
                // proactive filter. multi_kolab_role_application is
                // reportable by the role's organizer only — enforced at the
                // resource/controller layer (Task 7), not here.
                'multi_kolab_event', 'multi_kolab_role', 'multi_kolab_role_application',
            ])],
            'target_id' => ['required', 'string', 'max:255'],
            'reported_profile_id' => ['nullable', 'string', 'exists:profiles,id'],
            'reason' => ['required', 'string', Rule::in(['spam', 'harassment', 'inappropriate', 'other'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
