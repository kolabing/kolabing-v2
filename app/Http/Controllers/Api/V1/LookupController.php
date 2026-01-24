<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    /**
     * Get the list of available cities.
     *
     * GET /api/v1/cities
     */
    public function cities(): JsonResponse
    {
        $cities = City::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CityResource::collection($cities),
            'meta' => [
                'total' => $cities->count(),
            ],
        ]);
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
                'value' => 'food_blogger',
                'label' => __('Food Blogger'),
                'description' => __('Food and dining content creators'),
            ],
            [
                'value' => 'lifestyle_influencer',
                'label' => __('Lifestyle Influencer'),
                'description' => __('Lifestyle and general content influencers'),
            ],
            [
                'value' => 'fitness_enthusiast',
                'label' => __('Fitness Enthusiast'),
                'description' => __('Fitness and wellness content creators'),
            ],
            [
                'value' => 'travel_blogger',
                'label' => __('Travel Blogger'),
                'description' => __('Travel and tourism content creators'),
            ],
            [
                'value' => 'photographer',
                'label' => __('Photographer'),
                'description' => __('Professional and hobbyist photographers'),
            ],
            [
                'value' => 'local_explorer',
                'label' => __('Local Explorer'),
                'description' => __('City guides and local experience creators'),
            ],
            [
                'value' => 'student',
                'label' => __('Student'),
                'description' => __('University and college students'),
            ],
            [
                'value' => 'professional',
                'label' => __('Professional'),
                'description' => __('Working professionals and freelancers'),
            ],
            [
                'value' => 'community_organizer',
                'label' => __('Community Organizer'),
                'description' => __('Event organizers and community builders'),
            ],
            [
                'value' => 'other',
                'label' => __('Other'),
                'description' => __('Other community member types'),
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
