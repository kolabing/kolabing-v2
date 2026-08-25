<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\UserType;
use App\Models\CollaborationReview;
use App\Models\Profile;
use App\Services\FriendshipService;
use App\Services\ProfileService;
use App\Support\CommunityIdentityMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Flat public profile resource - no sensitive data (email, phone).
 *
 * **The identity mask is applied here.** ROLES §2.5 lists "open a community's full
 * profile or contact" among the things a free business cannot do, and this resource
 * had no subscription check at all — so the identity a business is meant to pay for
 * was one `GET /profiles/{id}` away for anyone holding an id, on every client
 * (BE-FX-22). The mobile app drew a blur over a payload that contained the real
 * name; the web panel drew nothing. Neither was a gate. This is the gate.
 *
 * Read {@see shouldMaskIdentity()} before touching it — the condition order matters
 * more than the condition.
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

        // Single (cached) reputation computation feeds both the top-level
        // completed_kolabs_count and the reputation block — no duplicate COUNT.
        $reputation = app(ProfileService::class)->getReputationSummary($this->resource);

        $masked = $this->shouldMaskIdentity($request);

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            // Withheld under the mask: a handle resolves the identity on its own,
            // both through this API and through kolabing.com/p/{handle}.
            'handle' => $masked ? null : $this->resource->handle,
            'identity_masked' => $masked,
            // Attendee profiles have no name/avatar of their own, so fall back to
            // the base `profiles` record (profiles.name / profiles.avatar_url).
            'display_name' => $masked ? null : ($extendedProfile?->name ?? $this->resource->name),
            'avatar_url' => $masked ? null : $logo,
            'logo_url' => $masked ? null : $logo,
            // `about` is where a community names itself in prose, so it goes with
            // the name rather than staying as "everything else".
            'about' => $masked ? null : $extendedProfile?->about,
            'type' => $rawType,
            'type_label' => $this->formatTypeLabel($rawType),
            'business_type' => $this->when($this->user_type === UserType::Business, fn () => $this->businessProfile?->primaryCategory()),
            'categories' => $this->when($this->user_type === UserType::Business, fn () => $businessCategories),
            // Attendees carry their city on the base profile; business/community
            // carry it on the extended profile. Prefer the extended profile's
            // city, fall back to the base profile's city.
            'city_name' => $extendedProfile?->city?->name ?? $this->resource->city?->name,
            // Contact is the thing §2.5 names alongside the profile itself, and
            // §4.2 calls it "the single strongest reason to register".
            'instagram' => $masked ? null : $extendedProfile?->instagram,
            'tiktok' => $masked || $this->user_type !== UserType::Community
                ? null
                : $extendedProfile?->tiktok,
            'website' => $masked ? null : $extendedProfile?->website,
            'profile_photo' => $masked ? null : $logo,
            'completed_kolabs_count' => $reputation['completed_kolabs_count'],
            'friend_status' => $this->resolveFriendStatus($request),
            'friends_count' => app(FriendshipService::class)->friendsCountFor($this->resource),
            // Each review names its reviewer and links to their profile (§4.2), so
            // the list is a directory of this community's partners. Masked, that is
            // an identity leak by another route.
            'recent_reviews' => $masked ? [] : $this->buildRecentReviews(),
            'reputation' => $reputation,
            // Past events carry partner names, dates and photographs — §4.2
            // withholds exactly that from a viewer who has not earned it.
            ...($masked ? [] : $this->portfolioFields()),
        ];
    }

    /**
     * Resolve friend_status for the authenticated viewer vs this profile.
     * Returns 'self' for own profile, 'none' when unauthenticated.
     */
    private function resolveFriendStatus(Request $request): string
    {
        $viewer = $request->user();

        if (! $viewer instanceof Profile) {
            return 'none';
        }

        return app(FriendshipService::class)->statusFor($viewer, $this->resource);
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
    /**
     * Delegated so the condition exists once — see {@see CommunityIdentityMask},
     * including why the guard order matters more than the guards.
     */
    private function shouldMaskIdentity(Request $request): bool
    {
        return CommunityIdentityMask::applies($request->user(), $this->resource);
    }

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

    /**
     * The public portfolio, present only when the controller hydrated it —
     * which it does for business and community profiles only. An attendee keeps
     * exactly the payload they had before, and their gallery stays private.
     *
     * @return array<string, mixed>
     */
    private function portfolioFields(): array
    {
        if (! array_key_exists('community_public_past_events', $this->resource->getAttributes())) {
            return [];
        }

        $pastEvents = $this->getAttribute('community_public_past_events') ?? [];

        return [
            'gallery' => $this->getAttribute('community_public_gallery') ?? [],
            'past_events' => $pastEvents,
            'past_events_count' => count($pastEvents),
        ];
    }
}
