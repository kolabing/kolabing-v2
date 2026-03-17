<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\City;
use App\Models\CitySuggestion;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => array_values($businessTypes),
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

        return response()->json([
            'success' => true,
            'data' => array_values($communityTypes),
            'meta' => [
                'total' => count($communityTypes),
            ],
        ]);
    }
}
