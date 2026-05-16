<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VenueType;
use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private const PHOTO_PREVIEW_MAX_WIDTH = 1600;

    /**
     * @var array<string, string>
     */
    private const BUSINESS_CATEGORY_MAP = [
        'cafe' => 'cafe',
        'coffee_shop' => 'cafe',
        'restaurant' => 'restaurant',
        'bar' => 'bar',
        'pub' => 'bar',
        'night_club' => 'bar',
        'bakery' => 'bakery',
        'coworking_space' => 'coworking',
        'gym' => 'gym',
        'fitness_center' => 'gym',
        'spa' => 'salon',
        'beauty_salon' => 'salon',
        'hair_care' => 'salon',
        'store' => 'retail',
        'shopping_mall' => 'retail',
        'clothing_store' => 'retail',
        'book_store' => 'retail',
        'boutique' => 'retail',
        'hotel' => 'hotel',
        'lodging' => 'hotel',
        'hostel' => 'hotel',
    ];

    /**
     * @var array<string, string>
     */
    private const VENUE_TYPE_MAP = [
        'restaurant' => VenueType::Restaurant->value,
        'cafe' => VenueType::Cafe->value,
        'coffee_shop' => VenueType::Cafe->value,
        'bakery' => VenueType::Cafe->value,
        'bar' => VenueType::BarLounge->value,
        'pub' => VenueType::BarLounge->value,
        'night_club' => VenueType::BarLounge->value,
        'hotel' => VenueType::Hotel->value,
        'lodging' => VenueType::Hotel->value,
        'hostel' => VenueType::Hotel->value,
        'coworking_space' => VenueType::Coworking->value,
        'gym' => VenueType::SportsFacility->value,
        'fitness_center' => VenueType::SportsFacility->value,
        'stadium' => VenueType::SportsFacility->value,
        'sports_club' => VenueType::SportsFacility->value,
        'event_venue' => VenueType::EventSpace->value,
        'conference_center' => VenueType::EventSpace->value,
        'convention_center' => VenueType::EventSpace->value,
        'banquet_hall' => VenueType::EventSpace->value,
        'store' => VenueType::RetailStore->value,
        'shopping_mall' => VenueType::RetailStore->value,
        'clothing_store' => VenueType::RetailStore->value,
        'book_store' => VenueType::RetailStore->value,
        'boutique' => VenueType::RetailStore->value,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function autocomplete(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $apiKey = config('services.google_places.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('Google Places autocomplete skipped: GOOGLE_PLACES_API_KEY is not configured.');

            return [];
        }

        $response = Http::withHeaders($this->headers())
            ->post('https://places.googleapis.com/v1/places:autocomplete', [
                'input' => $query,
                'includedRegionCodes' => ['es'],
                'languageCode' => 'es',
                'locationBias' => [
                    'circle' => [
                        'center' => [
                            'latitude' => 41.3874,
                            'longitude' => 2.1686,
                        ],
                        'radius' => 50000.0,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Google Places autocomplete request failed.', [
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $suggestions = $response->json('suggestions', []);

        if (empty($suggestions)) {
            Log::info('Google Places autocomplete returned zero suggestions.', [
                'query' => $query,
            ]);
        }

        return array_values(array_filter(array_map(function (array $suggestion): ?array {
            $prediction = $suggestion['placePrediction'] ?? null;

            if (! is_array($prediction) || empty($prediction['placeId'])) {
                return null;
            }

            $details = $this->placeDetails($prediction['placeId']);
            $mainText = $prediction['structuredFormat']['mainText']['text'] ?? $prediction['text']['text'] ?? null;
            $secondaryText = $prediction['structuredFormat']['secondaryText']['text'] ?? null;

            return [
                'place_id' => $prediction['placeId'],
                'title' => $mainText,
                'subtitle' => $secondaryText,
                'formatted_address' => $details['formatted_address'] ?? $secondaryText,
                'city' => $details['city'] ?? null,
                'country' => $details['country'] ?? null,
                'latitude' => $details['latitude'] ?? null,
                'longitude' => $details['longitude'] ?? null,
            ];
        }, $suggestions)));
    }

    /**
     * @return array<string, mixed>
     */
    public function importablePlaceDetails(string $placeId): array
    {
        if ($placeId === '' || ! $this->hasApiKey()) {
            return [];
        }

        $payload = $this->fetchPlaceDetails($placeId, [
            'id',
            'displayName',
            'formattedAddress',
            'location',
            'addressComponents',
            'internationalPhoneNumber',
            'nationalPhoneNumber',
            'websiteUri',
            'regularOpeningHours',
            'currentOpeningHours',
            'types',
            'editorialSummary',
            'photos',
            'priceLevel',
            'rating',
            'userRatingCount',
        ]);

        if ($payload === []) {
            return [];
        }

        $city = $this->findAddressComponent($payload['addressComponents'] ?? [], 'locality');
        $country = $this->findAddressComponent($payload['addressComponents'] ?? [], 'country');
        $categories = $this->mapBusinessCategories($payload['types'] ?? []);
        $venueType = $this->mapVenueType($payload['types'] ?? []);
        $description = $payload['editorialSummary']['text'] ?? null;
        $phoneNumber = $payload['internationalPhoneNumber'] ?? $payload['nationalPhoneNumber'] ?? null;
        $website = $payload['websiteUri'] ?? null;
        $openingHours = $payload['regularOpeningHours']['weekdayDescriptions']
            ?? $payload['currentOpeningHours']['weekdayDescriptions']
            ?? [];

        return [
            'place_id' => $payload['id'] ?? $placeId,
            'name' => $payload['displayName']['text'] ?? null,
            'about' => $description,
            'business_type' => $categories[0] ?? 'other',
            'categories' => $categories,
            'city' => $city,
            'country' => $country,
            'phone_number' => $phoneNumber,
            'website' => $website,
            'formatted_address' => $payload['formattedAddress'] ?? null,
            'latitude' => $payload['location']['latitude'] ?? null,
            'longitude' => $payload['location']['longitude'] ?? null,
            'venue_type' => $venueType,
            'opening_hours' => is_array($openingHours) ? array_values($openingHours) : [],
            'description' => $description,
            'price_level' => $payload['priceLevel'] ?? null,
            'rating' => $payload['rating'] ?? null,
            'user_ratings_total' => $payload['userRatingCount'] ?? null,
            'google_place_types' => array_values(array_filter($payload['types'] ?? [], fn ($type) => is_string($type) && $type !== '')),
            'photos' => $this->formatImportablePhotos($payload['photos'] ?? []),
        ];
    }

    public function hasApiKey(): bool
    {
        $apiKey = config('services.google_places.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    public function isPhotoResourceName(string $value): bool
    {
        return preg_match('#^places/[^/]+/photos/[^/]+$#', $value) === 1;
    }

    public function photoUri(string $photoName, int $maxWidth = self::PHOTO_PREVIEW_MAX_WIDTH): ?string
    {
        if (! $this->isPhotoResourceName($photoName) || ! $this->hasApiKey()) {
            return null;
        }

        $response = Http::withHeaders($this->headers())
            ->get("https://places.googleapis.com/v1/{$photoName}/media", [
                'maxWidthPx' => max(1, min($maxWidth, 4800)),
                'skipHttpRedirect' => true,
            ]);

        if (! $response->successful()) {
            Log::warning('Google Places photo media request failed.', [
                'photo_name' => $photoName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('photoUri');
    }

    /**
     * @return array<string, mixed>
     */
    private function placeDetails(string $placeId): array
    {
        $payload = $this->fetchPlaceDetails($placeId, [
            'formattedAddress',
            'location',
            'addressComponents',
        ]);

        return [
            'formatted_address' => $payload['formattedAddress'] ?? null,
            'city' => $this->findAddressComponent($payload['addressComponents'] ?? [], 'locality'),
            'country' => $this->findAddressComponent($payload['addressComponents'] ?? [], 'country'),
            'latitude' => $payload['location']['latitude'] ?? null,
            'longitude' => $payload['location']['longitude'] ?? null,
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function fetchPlaceDetails(string $placeId, array $fields): array
    {
        if ($placeId === '' || ! $this->hasApiKey()) {
            if ($placeId !== '' && ! $this->hasApiKey()) {
                Log::warning('Google Places details skipped: GOOGLE_PLACES_API_KEY is not configured.');
            }

            return [];
        }

        $response = Http::withHeaders(array_merge($this->headers(), [
            'X-Goog-FieldMask' => implode(',', $fields),
        ]))->get("https://places.googleapis.com/v1/places/{$placeId}");

        if (! $response->successful()) {
            Log::warning('Google Places details request failed.', [
                'place_id' => $placeId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $photos
     * @return array<int, array<string, mixed>>
     */
    private function formatImportablePhotos(array $photos): array
    {
        return array_values(array_map(function (array $photo): array {
            $photoName = $photo['name'] ?? '';

            return [
                'resource_name' => $photoName,
                'preview_url' => route('api.v1.places.photo', ['name' => $photoName]),
                'width' => $photo['widthPx'] ?? null,
                'height' => $photo['heightPx'] ?? null,
                'author_attributions' => array_values(array_map(fn (array $attribution): array => [
                    'display_name' => $attribution['displayName'] ?? null,
                    'uri' => $attribution['uri'] ?? null,
                    'photo_uri' => $attribution['photoUri'] ?? null,
                ], $photo['authorAttributions'] ?? [])),
            ];
        }, array_values(array_filter($photos, fn (array $photo): bool => isset($photo['name']) && is_string($photo['name']) && $photo['name'] !== ''))));
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    private function mapBusinessCategories(array $types): array
    {
        $categories = [];

        foreach ($types as $type) {
            if (! is_string($type)) {
                continue;
            }

            $mapped = self::BUSINESS_CATEGORY_MAP[$type] ?? null;

            if ($mapped !== null && in_array($mapped, BusinessOnboardingRequest::BUSINESS_TYPES, true) && ! in_array($mapped, $categories, true)) {
                $categories[] = $mapped;
            }
        }

        return $categories !== [] ? array_slice($categories, 0, 3) : ['other'];
    }

    /**
     * @param  array<int, string>  $types
     */
    private function mapVenueType(array $types): string
    {
        foreach ($types as $type) {
            if (! is_string($type)) {
                continue;
            }

            $mapped = self::VENUE_TYPE_MAP[$type] ?? null;

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return VenueType::Other->value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function findAddressComponent(array $components, string $type): ?string
    {
        foreach ($components as $component) {
            if (in_array($type, $component['types'] ?? [], true)) {
                return $component['longText'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $apiKey = config('services.google_places.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $headers['X-Goog-Api-Key'] = $apiKey;
        }

        return $headers;
    }
}
