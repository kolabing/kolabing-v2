<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCollaborationFeedbackRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'expectation_match' => ['required', 'boolean'],
            'would_recommend' => ['required', 'boolean'],
            // "Would you kolab again?" — first-class feedback question; this is
            // what gets mirrored into the public collaboration_reviews row.
            'would_collaborate_again' => ['required', 'boolean'],
            'posts_reels' => ['nullable', 'integer', 'min:0', 'max:10000'],

            // Business-only.
            'stories_posted' => [$isBusiness ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:10000'],
            'revenue' => [$isBusiness ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:9999999.99'],

            // Community-only.
            'benefits' => [$isBusiness ? 'prohibited' : 'nullable', 'string', 'max:2000'],
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
