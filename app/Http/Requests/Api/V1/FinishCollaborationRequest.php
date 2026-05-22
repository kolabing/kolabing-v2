<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the feedback payload that MUST accompany a collaboration finish
 * (ROLES-AND-PERMISSIONS.md §4). Feedback is required to finish, so the core
 * fields are mandatory. Required fields differ by the caller's role:
 *
 *  - Business reviewer: rating, stories_posted, posts_reels, revenue,
 *    expectation_match, would_recommend.
 *  - Community reviewer: rating, benefits, posts_reels, expectation_match,
 *    would_recommend.
 */
class FinishCollaborationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Participant authorization is enforced by the controller policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            // Shared, always required (feedback is mandatory to finish).
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'posts_reels' => ['required', 'integer', 'min:0', 'max:65535'],
            'expectation_match' => ['required', 'boolean'],
            'would_recommend' => ['required', 'boolean'],

            // Optional free-text note carried onto the public review.
            'note' => ['nullable', 'string', 'max:200'],

            // Business-only.
            'stories_posted' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'revenue' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            // Community-only.
            'benefits' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->reviewerIsBusiness()) {
            $rules['stories_posted'] = ['required', 'integer', 'min:0', 'max:65535'];
            $rules['revenue'] = ['required', 'numeric', 'min:0', 'max:9999999999'];
        } elseif ($this->reviewerIsCommunity()) {
            $rules['benefits'] = ['required', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * Whether the authenticated reviewer is a business.
     */
    public function reviewerIsBusiness(): bool
    {
        $profile = $this->user();

        return $profile instanceof Profile && $profile->isBusiness();
    }

    /**
     * Whether the authenticated reviewer is a community.
     */
    public function reviewerIsCommunity(): bool
    {
        $profile = $this->user();

        return $profile instanceof Profile && $profile->isCommunity();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => __('validation.required', ['attribute' => 'rating']),
            'rating.integer' => __('validation.integer', ['attribute' => 'rating']),
            'rating.min' => __('validation.min.numeric', ['attribute' => 'rating', 'min' => 1]),
            'rating.max' => __('validation.max.numeric', ['attribute' => 'rating', 'max' => 5]),
            'posts_reels.required' => __('validation.required', ['attribute' => 'posts_reels']),
            'expectation_match.required' => __('validation.required', ['attribute' => 'expectation_match']),
            'would_recommend.required' => __('validation.required', ['attribute' => 'would_recommend']),
            'stories_posted.required' => __('validation.required', ['attribute' => 'stories_posted']),
            'revenue.required' => __('validation.required', ['attribute' => 'revenue']),
            'benefits.required' => __('validation.required', ['attribute' => 'benefits']),
            'note.max' => __('validation.max.string', ['attribute' => 'note', 'max' => 200]),
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
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
