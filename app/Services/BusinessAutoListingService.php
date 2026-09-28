<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lists every business in Explore from the moment it exists.
 *
 * Communities only see published venue/product Kolabs, and a business that
 * never writes one is invisible to them — which is how production reached
 * 111 Kolabs with 5 from businesses and an empty community Explore
 * (2026-09-28). So the platform keeps one listing per business on its behalf:
 * published, open-ended (no availability window, so it never expires out of
 * the feed), composed from the business profile, and marked `is_auto_listing`.
 *
 * Rules:
 * - One per business, ever. Once an auto listing exists in any status it is
 *   never created again, so a business that closes it stays closed.
 * - Skipped while the business already has an open venue/product offer of its
 *   own; the feed does not need a second card.
 * - Venue businesses (a primary venue on file) get a venue_promotion; the rest
 *   a product_promotion, because "host your community at" is false for a brand
 *   with no room.
 * - Created through KolabService, so venue enrichment and city
 *   canonicalisation run exactly as for a Kolab the owner writes.
 *
 * Also fills an empty avatar from the business's top Google Maps photo; an
 * avatar that is already set is never touched.
 */
class BusinessAutoListingService
{
    public const SKIP_NOT_BUSINESS = 'not_business';

    public const SKIP_INACTIVE = 'inactive_or_deleted';

    public const SKIP_TEST_USER = 'test_user';

    public const SKIP_NO_BUSINESS_PROFILE = 'no_business_profile';

    public const SKIP_NO_NAME = 'no_name';

    public const SKIP_NO_CITY = 'no_city';

    public const SKIP_AUTO_LISTING_EXISTS = 'auto_listing_exists';

    public const SKIP_HAS_OPEN_KOLAB = 'has_open_kolab';

    private const SUPPORTED_LOCALES = ['en', 'es', 'ca'];

    private const SPANISH_SPEAKING_COUNTRIES = ['spain', 'españa', 'espana', 'mexico', 'méxico'];

    private const MAX_MEDIA = 6;

    public function __construct(
        private readonly KolabService $kolabService,
    ) {}

    /**
     * Fill the avatar and create the listing, never throwing: onboarding must
     * not fail because a listing could not be composed. Wrapped in its own
     * transaction (a savepoint when nested) so a failed insert cannot poison a
     * surrounding Postgres transaction.
     */
    public function provisionSafely(Profile $profile): ?Kolab
    {
        try {
            return DB::transaction(fn (): ?Kolab => $this->provision($profile));
        } catch (\Throwable $e) {
            Log::error('Failed to auto-list business', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fill the avatar, then create the listing when the business is eligible.
     * Returns the new listing, or null when it was skipped.
     */
    public function provision(Profile $profile): ?Kolab
    {
        $this->applyMapsAvatarFallback($profile);

        if ($this->skipReason($profile) !== null) {
            return null;
        }

        return $this->kolabService->createAutoListing($profile, $this->buildPayload($profile));
    }

    /**
     * Why this profile gets no listing now, or null when it should get one.
     */
    public function skipReason(Profile $profile): ?string
    {
        if (! $profile->isBusiness()) {
            return self::SKIP_NOT_BUSINESS;
        }

        if ($profile->trashed() || $profile->is_active === false) {
            return self::SKIP_INACTIVE;
        }

        if ($profile->is_test_user) {
            return self::SKIP_TEST_USER;
        }

        $businessProfile = $this->businessProfileOf($profile);

        if ($businessProfile === null) {
            return self::SKIP_NO_BUSINESS_PROFILE;
        }

        if ($this->businessName($businessProfile) === null) {
            return self::SKIP_NO_NAME;
        }

        if ($this->cityOf($businessProfile) === null) {
            return self::SKIP_NO_CITY;
        }

        if (Kolab::query()->where('creator_profile_id', $profile->id)->autoListing()->exists()) {
            return self::SKIP_AUTO_LISTING_EXISTS;
        }

        if (Kolab::query()->where('creator_profile_id', $profile->id)->openBusinessOffer()->exists()) {
            return self::SKIP_HAS_OPEN_KOLAB;
        }

        return null;
    }

    /**
     * The photo that would become the avatar, or null when the avatar is
     * already set or there is nothing to use. An uploaded logo
     * (`business_profiles.profile_photo`) wins over any Maps photo.
     */
    public function avatarCandidate(Profile $profile): ?string
    {
        if (is_string($profile->avatar_url) && trim($profile->avatar_url) !== '') {
            return null;
        }

        $businessProfile = $this->businessProfileOf($profile);

        if ($businessProfile === null) {
            return null;
        }

        if (is_string($businessProfile->profile_photo) && filter_var($businessProfile->profile_photo, FILTER_VALIDATE_URL)) {
            return $businessProfile->profile_photo;
        }

        return $this->photoUrls($businessProfile)[0] ?? null;
    }

    /**
     * Give a business with no avatar its logo, or else its top Google Maps
     * photo. Returns whether anything was written.
     */
    public function applyMapsAvatarFallback(Profile $profile): bool
    {
        $candidate = $this->avatarCandidate($profile);

        if ($candidate === null) {
            return false;
        }

        $businessProfile = $this->businessProfileOf($profile);

        if ($businessProfile !== null && ! $this->hasText($businessProfile->profile_photo)) {
            $businessProfile->forceFill(['profile_photo' => $candidate])->save();
        }

        $profile->forceFill(['avatar_url' => $candidate])->save();

        return true;
    }

    /**
     * The KolabService::create() payload for this business's listing.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(Profile $profile): array
    {
        $businessProfile = $this->businessProfileOf($profile);
        $name = (string) $this->businessName($businessProfile);
        $locale = $this->localeFor($profile, $businessProfile);
        $venue = is_array($businessProfile->primary_venue) && $businessProfile->primary_venue !== []
            ? $businessProfile->primary_venue
            : null;
        $kind = $venue !== null ? 'venue' : 'product';

        $offering = $this->hasText($businessProfile->offering) ? trim((string) $businessProfile->offering) : null;
        $media = [];
        foreach (array_slice($this->photoUrls($businessProfile), 0, self::MAX_MEDIA) as $order => $url) {
            $media[] = ['url' => $url, 'type' => 'image', 'sort_order' => $order];
        }

        $payload = [
            'intent_type' => $venue !== null ? IntentType::VenuePromotion->value : IntentType::ProductPromotion->value,
            'title' => Str::limit(__("auto_listing.title_{$kind}", ['name' => $name], $locale), 255, ''),
            'description' => $this->buildDescription($businessProfile, $name, $kind, $venue, $offering, $locale),
            'offer_headline' => __("auto_listing.offer_headline_{$kind}", [], $locale),
            'base_offer' => $offering ?? __("auto_listing.base_offer_{$kind}", ['name' => $name], $locale),
            'offering' => [$venue !== null ? 'venue' : 'products'],
            'preferred_city' => $this->cityOf($businessProfile),
            'media' => $media === [] ? null : $media,
        ];

        if ($venue === null) {
            $payload['product_name'] = Str::limit($name, 255, '');
            $payload['product_type'] = $this->hasText($businessProfile->product_type) ? $businessProfile->product_type : 'other';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $venue
     */
    private function buildDescription(
        BusinessProfile $businessProfile,
        string $name,
        string $kind,
        ?array $venue,
        ?string $offering,
        string $locale,
    ): string {
        $lines = [];

        $about = $this->hasText($businessProfile->about)
            ? trim((string) $businessProfile->about)
            : ($this->hasText($venue['description'] ?? null) ? trim((string) $venue['description']) : null);

        if ($about !== null) {
            $lines[] = $about;
        }

        if ($offering !== null) {
            $lines[] = __('auto_listing.what_we_offer', ['offering' => $offering], $locale);
        }

        $categories = $this->categoryLabels($businessProfile);
        if ($categories !== []) {
            $lines[] = __('auto_listing.categories', ['categories' => implode(', ', $categories)], $locale);
        }

        if ($venue !== null) {
            $venueParts = array_values(array_filter([
                $this->hasText($venue['name'] ?? null) ? trim((string) $venue['name']) : null,
                $this->hasText($venue['formatted_address'] ?? null) ? trim((string) $venue['formatted_address']) : null,
            ]));

            if ($venueParts !== []) {
                $lines[] = __('auto_listing.venue', ['venue' => implode(', ', $venueParts)], $locale);
            }

            $capacity = isset($venue['capacity']) && is_numeric($venue['capacity']) ? (int) $venue['capacity'] : 0;
            if ($capacity > 0) {
                $lines[] = __('auto_listing.capacity', ['capacity' => $capacity], $locale);
            }
        }

        $lines[] = __("auto_listing.closing_{$kind}", ['name' => $name], $locale);

        return implode("\n\n", $lines);
    }

    /**
     * Human labels for the business's categories, from the admin-managed
     * business_types table; an unknown slug is shown as a headline.
     *
     * @return array<int, string>
     */
    private function categoryLabels(BusinessProfile $businessProfile): array
    {
        $slugs = $businessProfile->normalizedCategories();

        if ($slugs === []) {
            return [];
        }

        $names = BusinessType::query()->whereIn('slug', $slugs)->pluck('name', 'slug');

        return array_values(array_map(
            fn (string $slug): string => (string) ($names[$slug] ?? Str::headline($slug)),
            $slugs,
        ));
    }

    /**
     * Every usable photo URL on the business, best first: the primary venue's
     * photos (Google Maps imports land here), then the offer photos. The admin
     * Maps import stores venue photos as objects carrying a `preview_url`; the
     * app's onboarding stores rehosted URL strings. Both are read.
     *
     * @return array<int, string>
     */
    private function photoUrls(BusinessProfile $businessProfile): array
    {
        $venuePhotos = is_array($businessProfile->primary_venue['photos'] ?? null)
            ? $businessProfile->primary_venue['photos']
            : [];
        $offerPhotos = is_array($businessProfile->offer_photos) ? $businessProfile->offer_photos : [];

        $urls = [];

        foreach ([...$venuePhotos, ...$offerPhotos] as $photo) {
            $url = is_array($photo) ? ($photo['url'] ?? $photo['preview_url'] ?? null) : $photo;

            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * The listing's language: the owner's chosen app language when it is one we
     * write in; otherwise Spanish for a business in a Spanish-speaking country;
     * otherwise English.
     */
    private function localeFor(Profile $profile, BusinessProfile $businessProfile): string
    {
        $preferred = is_string($profile->preferred_locale)
            ? strtolower(substr($profile->preferred_locale, 0, 2))
            : null;

        if ($preferred !== null && in_array($preferred, self::SUPPORTED_LOCALES, true)) {
            return $preferred;
        }

        $country = $businessProfile->city_country
            ?? $businessProfile->city?->country
            ?? ($businessProfile->primary_venue['country'] ?? null);

        if (is_string($country) && in_array(mb_strtolower(trim($country)), self::SPANISH_SPEAKING_COUNTRIES, true)) {
            return 'es';
        }

        return 'en';
    }

    private function cityOf(BusinessProfile $businessProfile): ?string
    {
        foreach ([
            $businessProfile->city_name,
            $businessProfile->city?->name,
            $businessProfile->primary_venue['city'] ?? null,
        ] as $candidate) {
            if ($this->hasText($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    private function businessName(?BusinessProfile $businessProfile): ?string
    {
        return $this->hasText($businessProfile?->name) ? trim((string) $businessProfile->name) : null;
    }

    private function businessProfileOf(Profile $profile): ?BusinessProfile
    {
        $profile->loadMissing('businessProfile');

        return $profile->businessProfile;
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
