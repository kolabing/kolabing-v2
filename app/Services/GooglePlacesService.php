<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

        $response = Http::withHeaders($this->headers())
            ->post('https://places.googleapis.com/v1/places:autocomplete', [
                'input' => $query,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $suggestions = $response->json('suggestions', []);

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
