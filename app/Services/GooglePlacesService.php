<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
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
    private function placeDetails(string $placeId): array
    {
        $response = Http::withHeaders(array_merge($this->headers(), [
            'X-Goog-FieldMask' => 'formattedAddress,location,addressComponents',
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

        return [
            'formatted_address' => $payload['formattedAddress'] ?? null,
            'city' => $this->findAddressComponent($payload['addressComponents'] ?? [], 'locality'),
            'country' => $this->findAddressComponent($payload['addressComponents'] ?? [], 'country'),
            'latitude' => $payload['location']['latitude'] ?? null,
            'longitude' => $payload['location']['longitude'] ?? null,
        ];
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
