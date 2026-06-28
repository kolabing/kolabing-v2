<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CollaborationReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollaborationReview
 */
class PublicProfileReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewer = $this->reviewerProfile;
        $extendedProfile = $reviewer?->getExtendedProfile();
        $avatar = $this->absoluteUrl($extendedProfile?->profile_photo ?? $reviewer?->avatar_url);

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'overall_rating' => $this->overall_rating,
            'body' => $this->body ?? $this->note,
            'public_comment' => $this->public_comment,
            'would_collaborate_again' => $this->would_collaborate_again,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewer' => [
                'id' => $reviewer?->id,
                'display_name' => $extendedProfile?->name,
                'avatar_url' => $avatar,
                'user_type' => $reviewer?->user_type?->value,
            ],
        ];
    }

    private function absoluteUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($value, '/');
    }
}
