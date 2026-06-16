<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\CitySuggestion;
use App\Models\CommunityType;
use App\Models\Profile;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __construct(
        private readonly GooglePlacesService $googlePlacesService
    ) {}

    /**
     * Get the list of available cities.
     * Returns only active cities by default. Pass ?all=true for full list.
     *
     * GET /api/v1/cities
     */
    public function cities(Request $request): JsonResponse
    {
        $query = City::query();

        if (! $request->boolean('all')) {
            $query->where('is_active', true);
        }

        $cities = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = CityResource::collection($cities)->resolve();

        // Append "Other / Suggest a city" virtual entry
        $data[] = [
            'id' => 'other',
            'name' => 'Other / Suggest a city',
            'country' => 'Spain',
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => count($data),
            ],
        ]);
    }

    /**
     * Submit a city suggestion.
     *
     * POST /api/v1/cities/suggest
     */
    public function suggestCity(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $validated = $request->validate([
            'city_name' => ['required', 'string', 'max:200'],
        ]);

        CitySuggestion::query()->create([
            'suggested_by' => $profile->id,
            'city_name' => $validated['city_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('City suggestion submitted. Thank you!'),
        ], 201);
    }

    /**
     * Get the list of available business types.
     *
     * GET /api/v1/lookup/business-types
     */
    public function businessTypes(): JsonResponse
    {
        $businessTypes = [
            [
                'value' => 'cafe',
                'label' => __('Cafe'),
                'description' => __('Coffee shops and cafeterias'),
            ],
            [
                'value' => 'restaurant',
                'label' => __('Restaurant'),
                'description' => __('Restaurants and dining establishments'),
            ],
            [
                'value' => 'bar',
                'label' => __('Bar'),
                'description' => __('Bars and pubs'),
            ],
            [
                'value' => 'bakery',
                'label' => __('Bakery'),
                'description' => __('Bakeries and pastry shops'),
            ],
            [
                'value' => 'coworking',
                'label' => __('Coworking Space'),
                'description' => __('Shared workspace and coworking facilities'),
            ],
            [
                'value' => 'gym',
                'label' => __('Gym/Fitness'),
                'description' => __('Gyms and fitness centers'),
            ],
            [
                'value' => 'salon',
                'label' => __('Salon/Spa'),
                'description' => __('Hair salons, beauty salons, and spas'),
            ],
            [
                'value' => 'retail',
                'label' => __('Retail Store'),
                'description' => __('Retail shops and boutiques'),
            ],
            [
                'value' => 'hotel',
                'label' => __('Hotel/Accommodation'),
                'description' => __('Hotels, hostels, and accommodations'),
            ],
            [
                'value' => 'other',
                'label' => __('Other'),
                'description' => __('Other business types'),
            ],
        ];

        // Validate that all values match the allowed business types
        $allowedValues = BusinessOnboardingRequest::BUSINESS_TYPES;
        $businessTypes = array_filter($businessTypes, fn ($type) => in_array($type['value'], $allowedValues, true));

        // Enrich each entry with icon + icon_url + applies_to from the seeded
        // business_types table so the app can render and goal-filter the category
        // pills. `icon_url` is the admin-picked personalised SVG (CategoryIcon
        // renders it with top priority). Falls back gracefully if a row is missing.
        $meta = BusinessType::query()
            ->get(['slug', 'icon', 'icon_url', 'applies_to'])
            ->keyBy('slug');

        $businessTypes = array_map(function (array $type) use ($meta): array {
            $row = $meta->get($type['value']);

            return [
                ...$type,
                'icon' => $row?->icon,
                'icon_url' => $row?->icon_url,
                'applies_to' => $row?->applies_to ?? 'both',
            ];
        }, array_values($businessTypes));

        return response()->json([
            'success' => true,
            'data' => $businessTypes,
            'meta' => [
                'total' => count($businessTypes),
            ],
        ]);
    }

    /**
     * Get the list of available community types.
     *
     * GET /api/v1/lookup/community-types
     */
    public function communityTypes(): JsonResponse
    {
        $communityTypes = [
            [
                'value' => 'run_club',
                'label' => __('Run Club'),
                'description' => __('Running clubs and groups'),
            ],
            [
                'value' => 'fitness_community',
                'label' => __('Fitness Community'),
                'description' => __('Fitness and sports communities'),
            ],
            [
                'value' => 'wellness_community',
                'label' => __('Wellness Community'),
                'description' => __('Wellness and health communities'),
            ],
            [
                'value' => 'art_creative_community',
                'label' => __('Art & Creative Community'),
                'description' => __('Art and creative communities'),
            ],
            [
                'value' => 'photography_community',
                'label' => __('Photography Community'),
                'description' => __('Photography enthusiasts and clubs'),
            ],
            [
                'value' => 'music_community',
                'label' => __('Music Community'),
                'description' => __('Music communities and groups'),
            ],
            [
                'value' => 'dance_community',
                'label' => __('Dance Community'),
                'description' => __('Dance communities and groups'),
            ],
            [
                'value' => 'tech_startup_community',
                'label' => __('Tech / Startup Community'),
                'description' => __('Tech and startup communities'),
            ],
            [
                'value' => 'book_club',
                'label' => __('Book Club'),
                'description' => __('Book clubs and reading groups'),
            ],
            [
                'value' => 'sustainability_community',
                'label' => __('Sustainability Community'),
                'description' => __('Sustainability and eco communities'),
            ],
            [
                'value' => 'food_community',
                'label' => __('Food Community'),
                'description' => __('Food and gastronomy communities'),
            ],
            [
                'value' => 'travel_community',
                'label' => __('Travel Community'),
                'description' => __('Travel and exploration communities'),
            ],
            [
                'value' => 'student_community',
                'label' => __('Student Community'),
                'description' => __('Student and university communities'),
            ],
            [
                'value' => 'professional_networking_community',
                'label' => __('Professional / Networking Community'),
                'description' => __('Professional and networking communities'),
            ],
            [
                'value' => 'business_coworking',
                'label' => __('Business / Coworking'),
                'description' => __('Founder dinners, coworking collectives, and business groups'),
            ],
            [
                'value' => 'hobby_community',
                'label' => __('Hobby Community'),
                'description' => __('Hobby and interest communities'),
            ],
            [
                'value' => 'other',
                'label' => __('Other'),
                'description' => __('Other community types'),
            ],
        ];

        // Validate that all values match the allowed community types
        $allowedValues = CommunityOnboardingRequest::COMMUNITY_TYPES;
        $communityTypes = array_filter($communityTypes, fn ($type) => in_array($type['value'], $allowedValues, true));

        // Enrich each entry with icon + icon_url from the seeded community_types
        // table so the app can render the admin-picked personalised SVG (CategoryIcon
        // renders icon_url with top priority). Communities have no venue/product
        // split, so no applies_to is emitted here. Falls back gracefully.
        $meta = CommunityType::query()
            ->get(['slug', 'icon', 'icon_url'])
            ->keyBy('slug');

        $communityTypes = array_map(function (array $type) use ($meta): array {
            $row = $meta->get($type['value']);

            return [
                ...$type,
                'icon' => $row?->icon,
                'icon_url' => $row?->icon_url,
            ];
        }, array_values($communityTypes));

        return response()->json([
            'success' => true,
            'data' => array_values($communityTypes),
            'meta' => [
                'total' => count($communityTypes),
            ],
        ]);
    }

    /**
     * Get the list of business offering options (what a business offers).
     *
     * GET /api/v1/lookup/offerings
     */
    public function offerings(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_OFFERING);
    }

    /**
     * Get the list of deliverable options (offered in return — community
     * offers_in_return / business expects).
     *
     * GET /api/v1/lookup/deliverables
     */
    public function deliverables(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_DELIVERABLE);
    }

    /**
     * Get the list of community need options.
     *
     * GET /api/v1/lookup/needs
     */
    public function needs(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_NEED);
    }

    /**
     * Get the list of product type options (kolab product-promotion + product
     * onboarding picker).
     *
     * GET /api/v1/lookup/product-types
     */
    public function productTypes(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_PRODUCT_TYPE);
    }

    /**
     * Get the list of venue type options (venue onboarding + kolab venue-promotion).
     *
     * GET /api/v1/lookup/venue-types
     */
    public function venueTypes(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_VENUE_TYPE);
    }

    /**
     * Map an active, sorted offer_options kind to the lookup response. Emits the
     * shared shape { value, label, icon, icon_url, is_active, sort_order } so every
     * offer / product / venue taxonomy is consumed by one app model. `icon_url` is the
     * full public URL of the personalised SVG the admin picked (null when unset); the
     * app's CategoryIcon widget renders it with top priority, falling back to `icon`.
     */
    private function offerOptionResponse(string $kind): JsonResponse
    {
        $data = \App\Models\OfferOption::query()
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['name', 'slug', 'icon', 'icon_url', 'is_active', 'sort_order'])
            ->map(fn ($o): array => [
                'value' => $o->slug,
                'label' => $o->name,
                'icon' => $o->icon,
                'icon_url' => $o->icon_url,
                'is_active' => (bool) $o->is_active,
                'sort_order' => (int) $o->sort_order,
            ])->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    /**
     * GET /api/v1/places/autocomplete
     */
    public function autocompletePlaces(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $places = array_map(function (array $place): array {
            $matchedCity = null;

            if (! empty($place['city'])) {
                $matchedCity = City::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $place['city'])])
                    ->first();
            }

            return [
                'place_id' => $place['place_id'],
                'title' => $place['title'],
                'subtitle' => $place['subtitle'],
                'formatted_address' => $place['formatted_address'],
                'city' => $place['city'],
                'country' => $place['country'],
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'city_id' => $matchedCity?->id,
            ];
        }, $this->googlePlacesService->autocomplete($validated['query']));

        return response()->json([
            'success' => true,
            'data' => $places,
        ]);
    }

    /**
     * GET /api/v1/places/details
     */
    public function placeDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
        ]);

        $details = $this->googlePlacesService->importablePlaceDetails($validated['place_id']);

        if ($details === []) {
            return response()->json([
                'success' => false,
                'message' => "We couldn't import from Google, please fill in manually.",
            ], 503);
        }

        $matchedCity = null;

        if (! empty($details['city'])) {
            $matchedCity = City::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $details['city'])])
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $details['name'],
                'about' => $details['about'],
                'business_type' => $details['business_type'],
                'categories' => $details['categories'],
                'city_id' => $matchedCity?->id,
                'city_name' => $details['city'],
                'phone_number' => $details['phone_number'],
                'website' => $details['website'],
                'primary_venue' => [
                    'name' => $details['name'],
                    'venue_type' => $details['venue_type'],
                    'capacity' => null,
                    'place_id' => $details['place_id'],
                    'formatted_address' => $details['formatted_address'],
                    'city' => $details['city'],
                    'country' => $details['country'],
                    'latitude' => $details['latitude'],
                    'longitude' => $details['longitude'],
                    'phone_number' => $details['phone_number'],
                    'website' => $details['website'],
                    'opening_hours' => $details['opening_hours'],
                    'description' => $details['description'],
                    'price_level' => $details['price_level'],
                    'rating' => $details['rating'],
                    'user_ratings_total' => $details['user_ratings_total'],
                    'google_place_types' => $details['google_place_types'],
                    'photos' => $details['photos'],
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/places/photo
     */
    public function placePhoto(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            // Google Places (New) photo resource names are long (commonly
            // 400-1000+ chars: "places/{id}/photos/{token}"). The old max:255
            // rejected every real photo with a 422 before photoUri() ran, so
            // imports silently failed. The resource-name FORMAT is still
            // enforced by GooglePlacesService::isPhotoResourceName().
            'name' => ['required', 'string', 'max:2048'],
            'max_width' => ['nullable', 'integer', 'min:1', 'max:4800'],
        ]);

        $photoUri = $this->googlePlacesService->photoUri(
            $validated['name'],
            (int) ($validated['max_width'] ?? 1600)
        );

        if (! is_string($photoUri) || $photoUri === '') {
            return response()->json([
                'success' => false,
                'message' => "We couldn't import from Google, please fill in manually.",
            ], 503);
        }

        return redirect()->away($photoUri);
    }
}
