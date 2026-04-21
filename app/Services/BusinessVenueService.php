<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use App\Models\City;
use Illuminate\Support\Facades\Log;

class BusinessVenueService
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Resolve a city by ID or fallback name.
     */
    public function resolveCity(?string $cityId, ?string $cityName): ?City
    {
        if ($cityId !== null && $cityId !== '') {
            return City::query()->find($cityId);
        }

        if ($cityName === null || $cityName === '') {
            return null;
        }

        return City::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityName)])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $existingPrimaryVenue
     * @return array<string, mixed>
     */
    public function normalizePrimaryVenue(array $data, string $profileId, ?array $existingPrimaryVenue = null): array
    {
        $photos = $data['photos'] ?? ($existingPrimaryVenue['photos'] ?? []);

        return [
            'name' => $data['name'],
            'venue_type' => $data['venue_type'],
            'capacity' => (int) $data['capacity'],
            'place_id' => $data['place_id'] ?? null,
            'formatted_address' => $data['formatted_address'],
            'city' => $data['city'],
            'country' => $data['country'] ?? null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'photos' => $this->normalizeVenuePhotos($photos, $profileId),
        ];
    }

    /**
     * @param  array<int, string>  $photos
     * @return array<int, string>
     */
    private function normalizeVenuePhotos(array $photos, string $profileId): array
    {
        return array_values(array_filter(array_map(function (string $photo) use ($profileId): ?string {
            if ($photo === '') {
                return null;
            }

            try {
                if (filter_var($photo, FILTER_VALIDATE_URL)) {
                    return $photo;
                }

                if (preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $photo) === 1
                    || base64_decode($photo, true) !== false) {
                    return $this->fileUploadService->uploadFromBase64(
                        $photo,
                        FileUploadType::GalleryPhoto,
                        $profileId
                    );
                }
            } catch (\Throwable $exception) {
                Log::warning('Failed to normalize venue photo', [
                    'profile_id' => $profileId,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }, $photos)));
    }
}
