<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

class SendPostHogEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly string $distinctId,
        public readonly string $event,
        public readonly array $properties = [],
    ) {
        if (config('queue.default') === 'sync') {
            $this->onConnection('database');
        }
    }

    public function handle(): void
    {
        if (! config('posthog.enabled')) {
            return;
        }

        $apiKey = config('posthog.api_key') ?: config('posthog.project_api_key');

        if (blank($apiKey)) {
            return;
        }

        try {
            $captured = PostHog::capture([
                'distinctId' => $this->distinctId,
                'event' => $this->event,
                'properties' => [
                    ...$this->properties,
                    'environment' => app()->environment(),
                    'event_source' => 'backend',
                    'source' => $this->properties['source'] ?? 'backend',
                ],
            ]);

            if ($captured !== true) {
                throw new \RuntimeException('PostHog capture request failed');
            }
        } catch (\Throwable $e) {
            Log::warning('PostHog capture request failed', [
                'event' => $this->event,
                'distinct_id' => $this->distinctId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            PostHog::flush();
        }
    }
}
