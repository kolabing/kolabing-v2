<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\HandleService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttendeeOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the handle (strip leading @, trim, lowercase) before validation
     * so format + uniqueness are checked against the stored form.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('handle'))) {
            $this->merge([
                'handle' => app(HandleService::class)->normalize($this->input('handle')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'regex:'.HandleService::FORMAT],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'interests' => ['nullable', 'array'],
            // Interests are the REAL 17-slug community-type vocabulary (the same
            // list communities pick at sign-up / GET /lookup/community-types),
            // NOT the 5-value App\Enums\CommunityType placeholder.
            'interests.*' => ['string', 'in:'.implode(',', CommunityOnboardingRequest::COMMUNITY_TYPES)],
            'community_ids' => ['nullable', 'array'],
            'community_ids.*' => ['uuid', 'exists:communities,id'],
            'photo' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The name field is required'),
            'handle.required' => __('A handle is required'),
            'handle.regex' => __('The handle must be 3 to 20 characters: lowercase letters, numbers, or underscores'),
            'interests.*.in' => __('One of the selected interests is invalid'),
            'community_ids.*.exists' => __('One of the selected communities does not exist'),
            'city_id.exists' => __('The selected city does not exist'),
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
