<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollaborationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in the controller via policy
    }

    /**
     * The new 5-star format is detected by the presence of any one of its
     * rating fields. When present, all five become required. Clients still
     * sending the legacy single `rating` payload are unaffected.
     */
    private function usesStarRatingFormat(): bool
    {
        foreach (\App\Models\CollaborationReview::STAR_RATING_FIELDS as $field) {
            if ($this->filled($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $starRatingRule = $this->usesStarRatingFormat() ? 'required' : 'nullable';

        return [
            'rating' => [$this->usesStarRatingFormat() ? 'nullable' : 'required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => [$starRatingRule, 'integer', 'min:1', 'max:5'],
            'reliability_rating' => [$starRatingRule, 'integer', 'min:1', 'max:5'],
            'fit_rating' => [$starRatingRule, 'integer', 'min:1', 'max:5'],
            'value_rating' => [$starRatingRule, 'integer', 'min:1', 'max:5'],
            'repeat_rating' => [$starRatingRule, 'integer', 'min:1', 'max:5'],
            'public_comment' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:500'],
            'would_collaborate_again' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'A star rating is required.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'communication_rating.required' => 'Please rate communication.',
            'reliability_rating.required' => 'Please rate reliability.',
            'fit_rating.required' => 'Please rate fit.',
            'value_rating.required' => 'Please rate value.',
            'repeat_rating.required' => 'Please rate whether you would Kolab again.',
            'public_comment.max' => 'Comment cannot exceed 2000 characters.',
            'body.max' => 'Review text cannot exceed 500 characters.',
        ];
    }
}
