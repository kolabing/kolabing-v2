<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OneSignalService
{
    /**
     * Send a transactional push notification to one or more app users.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function sendPushToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $normalizedUserIds = collect($userIds)
            ->filter(static fn (int|string|null $userId): bool => filled($userId))
            ->map(static fn (int|string $userId): string => (string) $userId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedUserIds === []) {
            throw new RuntimeException('OneSignal push requires at least one target user id.');
        }

        $appId = (string) config('services.onesignal.app_id');
        $apiKey = (string) config('services.onesignal.rest_api_key');
        $baseUrl = (string) config('services.onesignal.base_url', 'https://api.onesignal.com');

        if ($appId === '' || $apiKey === '') {
            throw new RuntimeException('OneSignal push is not configured.');
        }

        $payload = [
            'app_id' => $appId,
            'include_aliases' => [
                'external_id' => $normalizedUserIds,
            ],
            'target_channel' => 'push',
            'headings' => [
                'en' => $title,
            ],
            'contents' => [
                'en' => $body,
            ],
            'data' => $data,
        ];

        Log::info('OneSignal: sending transactional push', [
            'external_ids' => $normalizedUserIds,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $response = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Key '.$apiKey,
            ])
            ->post('/notifications', $payload);

        $responseData = $response->json();

        if ($response->failed()) {
            Log::error('OneSignal: push request failed', [
                'external_ids' => $normalizedUserIds,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            $response->throw();
        }

        if (! is_array($responseData)) {
            $responseData = [];
        }

        Log::info('OneSignal: transactional push accepted', [
            'external_ids' => $normalizedUserIds,
            'message_id' => $responseData['id'] ?? null,
            'response' => $responseData,
        ]);

        if (! array_key_exists('id', $responseData)) {
            Log::warning('OneSignal: request accepted without message id', [
                'external_ids' => $normalizedUserIds,
                'response' => $responseData,
            ]);
        }

        return $responseData;
    }
}
