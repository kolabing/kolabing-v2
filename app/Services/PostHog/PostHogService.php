<?php

declare(strict_types=1);

namespace App\Services\PostHog;

use App\Jobs\SendPostHogEvent;
use App\Models\Profile;

class PostHogService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function capture(Profile|string $profileOrDistinctId, string $event, array $properties = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        if ($profileOrDistinctId instanceof Profile && $profileOrDistinctId->analytics_opt_out) {
            return;
        }

        [$distinctId, $safeProperties] = $this->resolveDistinctIdAndProperties($profileOrDistinctId);

        SendPostHogEvent::dispatch(
            distinctId: $distinctId,
            event: $event,
            properties: [
                ...$safeProperties,
                ...$properties,
            ],
        );
    }

    public function isConfigured(): bool
    {
        return (bool) config('posthog.enabled') && filled(config('posthog.project_api_key'));
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolveDistinctIdAndProperties(Profile|string $profileOrDistinctId): array
    {
        if (is_string($profileOrDistinctId)) {
            return [$profileOrDistinctId, []];
        }

        return [
            (string) $profileOrDistinctId->id,
            [
                'user_id' => (string) $profileOrDistinctId->id,
                'user_type' => $profileOrDistinctId->user_type?->value,
            ],
        ];
    }
}
