<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
 * where the value starts. Contact details, the full review list, reviewer
 * identities, past-event detail and collaboration partners are the reason to create
 * an account, so they are not merely hidden with CSS: they never reach the HTML.
 * Opening hours are the one exception (added 2026-09-15): purely operational, not
 * personal/contact info, and every benchmarked listing platform (Yelp/Google
 * Business Profile/TripAdvisor) shows them on every listing.
 *
 * It reads models directly rather than calling /api/v1, which keeps the API
 * authenticated. Nothing here may become a way to enumerate the database.
 *
 * "Potential collaborations" (added 2026-09-15, Daniel: "you are missing a
 * potential collaborations section of social proof and cta") is a COUNT, not the
 * real match list: it deliberately does NOT run the authenticated Suggestions
 * pipeline (PairCandidateFinder/SignalScorer) -- that is Kolabing's actual matching
 * algorithm, expensive to compute and itself part of the value an account buys.
 * Running it for every anonymous, crawlable page view would both leak proprietary
 * signal (exactly who scores well against whom) and add real load to an unauthed
 * surface. A plain active-profile count in the same city is real social proof
 * without either cost.
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
            'openingHours' => $this->openingHours($extended),
            'potentialCollaborationCount' => $this->potentialCollaborationCount($profile),
        ]);
    }

    /**
     * How many active, real (non-test) profiles of the OPPOSITE type share this
     * profile's city — real social proof that the platform has people to meet here,
     * without running the actual matching algorithm (see class doc comment).
     */
    private function potentialCollaborationCount(Profile $profile): int
    {
        $cityId = $profile->isBusiness()
            ? $profile->businessProfile?->city_id
            : $profile->communityProfile?->city_id;

        if (! is_string($cityId) || $cityId === '') {
            return 0;
        }

        $query = $profile->isBusiness()
            ? CommunityProfile::query()->where('city_id', $cityId)
            : BusinessProfile::query()->where('city_id', $cityId);

        // ActiveProfileScope (a default scope on both models) already excludes
        // is_active=false; is_test_user mirrors the same exclusion ManagedUserController
        // uses for its own city counts, so a QA seed row never inflates real social proof.
        return (int) $query->whereHas('profile', fn ($q) => $q->where('is_test_user', false))->count();
    }

    /**
     * @return array<int, string>
     */
    private function openingHours(mixed $extended): array
    {
        if (! $extended instanceof BusinessProfile || ! is_array($extended->opening_hours)) {
            return [];
        }

        return array_values(array_filter($extended->opening_hours, fn ($line): bool => is_string($line) && $line !== ''));
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
