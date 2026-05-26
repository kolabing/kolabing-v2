<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CollaborationStatus;
use App\Enums\UserType;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Flat public profile resource - no sensitive data (email, phone).
 *
 * @mixin Profile
 */
class PublicProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->getExtendedProfile();
        $businessCategories = $this->isBusiness()
            ? $this->businessProfile?->normalizedCategories() ?? []
            : [];

        $rawType = $this->user_type === UserType::Business
            ? $this->businessProfile?->primaryCategory()
            : $extendedProfile?->community_type;

        // Logo: prefer the extended profile photo column, fall back to the base
        // avatar_url. Always serialize as an absolute URL.
        $logo = $this->absoluteUrl($extendedProfile?->profile_photo ?? $this->avatar_url);

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $extendedProfile?->name,
            'avatar_url' => $logo,
            'logo_url' => $logo,
            'about' => $extendedProfile?->about,
            'type' => $rawType,
            'type_label' => $this->formatTypeLabel($rawType),
            'business_type' => $this->when($this->user_type === UserType::Business, fn () => $this->businessProfile?->primaryCategory()),
            'categories' => $this->when($this->user_type === UserType::Business, fn () => $businessCategories),
            'city_name' => $extendedProfile?->city?->name,
            'instagram' => $extendedProfile?->instagram,
            'tiktok' => $this->user_type === UserType::Community
                ? $extendedProfile?->tiktok
                : null,
            'website' => $extendedProfile?->website,
            'profile_photo' => $logo,
            'completed_kolabs_count' => Collaboration::query()
                ->where('status', CollaborationStatus::Completed)
                ->where(fn ($q) => $q
                    ->where('creator_profile_id', $this->id)
                    ->orWhere('applicant_profile_id', $this->id)
                )
                ->count(),
            'recent_reviews' => $this->buildRecentReviews(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentReviews(): array
    {
        return PublicProfileReviewResource::collection(
            CollaborationReview::query()
            ->where('reviewed_profile_id', $this->id)
            ->whereNotNull('rating')
            ->with([
                'reviewerProfile.businessProfile',
                'reviewerProfile.communityProfile',
            ])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
        )->resolve();
    }

    /**
     * Convert a stored logo/avatar value into an absolute URL. Relative paths
     * are prefixed with the configured app URL. Already-absolute URLs and null
     * values are returned unchanged.
     */
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

    /**
     * Format a raw business/community type slug into a human label.
     * Single source of truth for profile type formatting (e.g. run_club ->
     * "Run Club", food_drink -> "Food & Drink").
     */
    private function formatTypeLabel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $key = Str::of($value)->lower()->replace([' ', '-'], '_')->trim('_')->value();

        return match ($key) {
            'food_drink', 'food_and_drink' => 'Food & Drink',
            default => Str::of($key)->replace('_', ' ')->title()->value(),
        };
    }
}
