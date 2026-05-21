<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator as ValidationValidator;

class PublishKolabRequest extends FormRequest
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
            'recipient_community_id' => ['sometimes', 'nullable', 'uuid', 'exists:profiles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_community_id.uuid' => __('validation.uuid', ['attribute' => 'recipient community']),
            'recipient_community_id.exists' => __('validation.exists', ['attribute' => 'recipient community']),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $recipientCommunityId = $this->input('recipient_community_id');
            if (! is_string($recipientCommunityId) || $recipientCommunityId === '') {
                return;
            }

            $recipient = Profile::query()->find($recipientCommunityId);
            if ($recipient === null || ! $recipient->isCommunity()) {
                $validator->errors()->add(
                    'recipient_community_id',
                    __('The recipient community must reference a community profile.')
                );
            }
        });
    }
}
