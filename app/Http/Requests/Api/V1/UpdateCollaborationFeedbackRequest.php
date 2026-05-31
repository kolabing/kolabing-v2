<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Same shape as Store, but every field is optional on PUT — the caller may
 * be correcting a single field. Role-only fields stay role-locked.
 */
class UpdateCollaborationFeedbackRequest extends FormRequest
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
        /** @var Profile|null $reviewer */
        $reviewer = $this->user();
        $isBusiness = $reviewer?->isBusiness() ?? false;

        return [
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'expectation_match' => ['sometimes', 'boolean'],
            'would_recommend' => ['sometimes', 'boolean'],
            'posts_reels' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],

            'stories_posted' => [$isBusiness ? 'sometimes' : 'prohibited', 'nullable', 'integer', 'min:0', 'max:10000'],
            'revenue' => [$isBusiness ? 'sometimes' : 'prohibited', 'nullable', 'numeric', 'min:0', 'max:9999999.99'],

            'benefits' => [$isBusiness ? 'prohibited' : 'sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stories_posted.prohibited' => 'Only business users can submit `stories_posted`.',
            'revenue.prohibited' => 'Only business users can submit `revenue`.',
            'benefits.prohibited' => 'Only community users can submit `benefits`.',
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
