<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\City;
use App\Models\CitySuggestion;
use App\Models\Profile;
use App\Services\GooglePlacesService;
use App\Services\HandleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __construct(
        private readonly GooglePlacesService $googlePlacesService,
        private readonly HandleService $handleService,
    ) {}

    /**
     * Check whether a `@handle` is available, returning suggestions on collision
     * or when the requested handle is malformed.
     *
     * GET /api/v1/handle/available?handle=<h>
     */
    public function handleAvailable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handle' => ['required', 'string', 'max:30'],
        ]);

        $normalized = $this->handleService->normalize($validated['handle']);

        if (! $this->handleService->isValidFormat($normalized)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => false,
                    'suggestions' => $this->handleService->suggestions($normalized),
                ],
            ]);
        }

        $available = $this->handleService->isAvailable($normalized);

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $available,
                'suggestions' => $available ? [] : $this->handleService->suggestions($normalized),
            ],
        ]);
    }

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

        // RETIRED 2026-06-10: the $businessTypes array above is kept (not
        // deleted) for reference only. Business types now live in the
        // business_types table (manage in /admin/types) — the source used here.
        // See docs/plans/2026-06-10-type-source-of-truth-DECISION.md.
        return $this->typeListResponse(\App\Models\BusinessType::query());
    }

    /**
     * Map an active, sorted *_types query to the lookup response. Emits both
     * value/label (legacy keys) and id/name/slug/icon/icon_url (current app),
     * so it stays compatible across app versions.
     */
    private function typeListResponse(\Illuminate\Database\Eloquent\Builder $query): JsonResponse
    {
        $data = $query->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'icon_url'])
            ->map(fn ($t): array => [
                'id' => $t->id,
                'value' => $t->slug,
                'slug' => $t->slug,
                'label' => $t->name,
                'name' => $t->name,
                'icon' => $t->icon,
                'icon_url' => $t->icon_url,
            ])->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => ['total' => count($data)],
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

        // RETIRED 2026-06-10: the $communityTypes array above is kept (not
        // deleted) for reference only. Community types now live in the
        // community_types table (manage in /admin/types) — the source used here.
        return $this->typeListResponse(\App\Models\CommunityType::query());
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
