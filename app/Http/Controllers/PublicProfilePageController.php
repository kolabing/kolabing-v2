<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\ProfileService;
use App\Support\PublicProfileLink;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * The shareable, indexable profile page on the marketing host
 * (`kolabing.com/p/{slug}`).
 *
 * This is a **teaser**, deliberately: it shows enough to be worth sharing and
 * ranking — who they are, what they are rated, a few photos, one quote — and stops
 * where the KOLABING-specific value starts. The full review list, reviewer
 * identities, past-event detail, collaboration partners, and in-app messaging are
 * the reason to create an account, so those never reach the HTML.
 *
 * Basic public business info (website, Instagram, phone, address) is a DIFFERENT
 * category: every benchmarked listing platform (Yelp, Google Business Profile,
 * TripAdvisor) shows this without gating it, because withholding it makes a first-
 * touch listing look unfinished/unreal rather than driving signup -- the entire
 * point of the listing-first strategy is that a stranger googling the business name
 * can tell it's a real, findable place. Added 2026-09-15 (Daniel: "fill this
 * specific listing to be better suited, use their ig"). "Message them directly" in
 * the wall below stays gated -- that's the actual Kolabing-specific value, not
 * contact info any business already publishes everywhere else.
 *
 * It reads models directly rather than calling /api/v1, which keeps the API
 * authenticated. Nothing here may become a way to enumerate the database.
 */
class PublicProfilePageController extends Controller
{
    /** How much of the "about" text a logged-out visitor gets. */
    private const ABOUT_PREVIEW_CHARS = 320;

    /** Photos on the public page; the rest sit behind sign-up. */
    private const PUBLIC_PHOTO_COUNT = 3;

    public function __construct(private readonly ProfileService $profileService) {}

    public function show(string $slug): View
    {
        $profile = PublicProfileLink::resolve($slug);

        abort_if($profile === null, 404);

        $detail = $this->profileService->getPublicProfileDetail($profile);
        $reputation = $this->profileService->getReputationSummary($profile);
        $stats = $detail->getAttribute('community_public_stats') ?? [];

        $canonicalSlug = PublicProfileLink::slugFor($profile);
        $extended = $profile->getExtendedProfile();
        $about = (string) ($extended?->about ?? '');
        $photos = $this->publicPhotos($detail);

        return view('pages.public-profile', [
            'profile' => $profile,
            'displayName' => $extended?->name ?? $profile->name ?? 'Kolabing member',
            'isBusiness' => $profile->isBusiness(),
            'typeLabel' => $this->typeLabel($profile),
            'cityName' => $extended?->city?->name ?? ($profile->isBusiness() ? $extended?->city_name : null),
            'avatarUrl' => $profile->avatar_url ?: $extended?->profile_photo,
            'aboutPreview' => Str::limit($about, self::ABOUT_PREVIEW_CHARS),
            'aboutIsTruncated' => mb_strlen($about) > self::ABOUT_PREVIEW_CHARS,
            'averageRating' => $reputation['average_rating'] ?? null,
            'reviewCount' => (int) ($reputation['review_count'] ?? 0),
            'completedKolabs' => (int) ($reputation['completed_kolabs_count'] ?? 0),
            'photos' => $photos,
            /*
             * A profile with no review and nothing to look at is a near-duplicate of
             * every other empty profile. It stays reachable (people share these links)
             * but asks not to be indexed, so a few hundred of them cannot turn into a
             * thin-page cluster. The same bar gates the sitemap — see routes/web.php.
             */
            'noindex' => ($reputation['review_count'] ?? 0) < 1 && count($photos) < 3,
            'hiddenPhotoCount' => max(0, count($detail->getAttribute('community_public_photos') ?? []) - self::PUBLIC_PHOTO_COUNT),
            'featuredReview' => $this->featuredReview($profile),
            'pastEventCount' => (int) ($stats['past_events_count'] ?? 0),
            'collaborationCount' => (int) ($stats['completed_collaborations_count'] ?? 0),
            'canonicalUrl' => url('/p/'.$canonicalSlug),
            'appUrl' => rtrim((string) config('webapp.url'), '/'),
            'website' => $this->publicWebsite($extended),
            'instagramUrl' => $this->publicInstagramUrl($extended),
            'phoneNumber' => $profile->phone_number,
            'address' => $extended instanceof BusinessProfile
                ? (string) ($extended->primary_venue['formatted_address'] ?? '')
                : '',
        ]);
    }

    private function publicWebsite(AttendeeProfile|BusinessProfile|CommunityProfile|null $extended): string
    {
        $website = ($extended instanceof BusinessProfile || $extended instanceof CommunityProfile)
            ? $extended->website
            : null;

        return is_string($website) ? $website : '';
    }

    /**
     * Stored as a bare handle or a full URL depending on how it was entered (the
     * admin form's `instagram` field has never constrained the format) -- normalise
     * either into a real profile URL rather than showing a bare "@handle" as if it
     * were clickable, or linking a raw handle string as a broken href.
     */
    private function publicInstagramUrl(AttendeeProfile|BusinessProfile|CommunityProfile|null $extended): string
    {
        $handle = ($extended instanceof BusinessProfile || $extended instanceof CommunityProfile)
            ? $extended->instagram
            : null;
        $raw = trim((string) $handle);

        if ($raw === '') {
            return '';
        }

        if (Str::startsWith($raw, ['http://', 'https://'])) {
            return $raw;
        }

        return 'https://instagram.com/'.ltrim($raw, '@');
    }

    /**
     * @return array<int, array{url: string, source: string}>
     */
    private function publicPhotos(Profile $detail): array
    {
        $photos = $detail->getAttribute('community_public_photos') ?? [];

        return array_slice(is_array($photos) ? $photos : [], 0, self::PUBLIC_PHOTO_COUNT);
    }

    /**
     * One quote to make the page worth reading — the newest review whose author
     * agreed to show the comment publicly. The reviewer stays anonymous here; who
     * vouched for whom is part of what an account buys.
     *
     * @return array{comment: string, rating: int|null, reviewer_type: string|null}|null
     */
    private function featuredReview(Profile $profile): ?array
    {
        /** @var CollaborationReview|null $review */
        $review = CollaborationReview::query()
            ->where('reviewed_profile_id', $profile->id)
            ->where('public_comment_visible', true)
            ->whereNotNull('public_comment')
            ->where('public_comment', '!=', '')
            ->with('reviewerProfile')
            ->orderByDesc('created_at')
            ->first();

        if ($review === null) {
            return null;
        }

        return [
            'comment' => (string) $review->public_comment,
            'rating' => $review->rating,
            'reviewer_type' => $review->reviewerProfile?->user_type?->value,
        ];
    }

    private function typeLabel(Profile $profile): ?string
    {
        $raw = $profile->isBusiness()
            ? $profile->businessProfile?->business_type
            : $profile->communityProfile?->community_type;

        if (! is_string($raw) || $raw === '') {
            return $profile->isBusiness() ? 'Business' : 'Community';
        }

        return Str::of($raw)->replace('_', ' ')->title()->toString();
    }
}
