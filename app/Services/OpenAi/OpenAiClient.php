<?php

declare(strict_types=1);

namespace App\Services\OpenAi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The one place that talks to OpenAI.
 *
 * Deliberately the plain HTTP client and not an SDK: the surface we need is two
 * endpoints, and adding a composer dependency to reach them would need approval for
 * no benefit. Keeping it behind this class also means `Http::fake()` is the whole
 * testing story — no service-provider swapping, no interface ceremony.
 *
 * Two rules the callers depend on:
 *
 *  - **A missing key is an error, not an empty result.** This generator writes copy
 *    that goes to real businesses under Kolabing's name; silently degrading to a
 *    blank pitch is the failure mode where nobody notices until a customer does.
 *  - **JSON responses are validated before they are returned.** The model is asked
 *    for JSON and usually obliges, but "usually" is not a contract, so a malformed
 *    body throws here rather than three layers up as an array-key warning.
 */
class OpenAiClient
{
    /**
     * Ask the text model for a JSON object.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function json(array $messages): array
    {
        $response = $this->request(config('services.openai.timeout'))
            ->post('/chat/completions', [
                'model' => config('services.openai.model'),
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            $this->fail('text', $response->status(), $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            $this->fail('text', $response->status(), 'the response carried no message content');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $this->fail('text', $response->status(), 'the model returned content that is not JSON');
        }

        return $decoded;
    }

    /**
     * Generate one image and return it as a base64 payload, ready for
     * {@see \App\Services\FileUploadService::uploadFromBase64()}.
     *
     * Returns a data URI rather than raw base64 because the upload service reads the
     * MIME type from the prefix to decide the extension; handing it bare base64 works
     * but stores every generated cover under a guessed type.
     *
     * @throws RuntimeException
     */
    public function image(string $prompt, string $size = '1536x1024'): string
    {
        $response = $this->request(config('services.openai.image_timeout'))
            ->post('/images/generations', [
                'model' => config('services.openai.image_model'),
                'prompt' => $prompt,
                'size' => $size,
                'n' => 1,
            ]);

        if (! $response->successful()) {
            $this->fail('image', $response->status(), $response->body());
        }

        $b64 = $response->json('data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            $this->fail('image', $response->status(), 'the response carried no image data');
        }

        return 'data:image/png;base64,'.$b64;
    }

    /**
     * Whether the integration is configured at all. The admin form asks this so it
     * can disable the generate button with a reason, instead of offering a control
     * that always fails.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    private function request(int $timeout): PendingRequest
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new RuntimeException(
                'OPENAI_API_KEY is not set, so no sales copy can be generated. '
                .'Add it to the environment before using this surface.'
            );
        }

        return Http::withToken((string) $key)
            ->baseUrl((string) config('services.openai.base_url'))
            ->timeout($timeout)
            // One retry, because a transient 429/5xx on an interactive click should
            // not make a maintainer redo the whole form. Not more: each attempt is
            // billed, and a persistently failing key should surface fast.
            ->retry(2, 1000, throw: false)
            ->acceptJson();
    }

    /**
     * @throws RuntimeException
     */
    private function fail(string $kind, int $status, string $detail): never
    {
        // The body can carry the prompt back; log it for diagnosis but keep the
        // thrown message short enough to show a maintainer verbatim.
        Log::error('OpenAI request failed', [
            'kind' => $kind,
            'status' => $status,
            'detail' => mb_substr($detail, 0, 1000),
        ]);

        throw new RuntimeException(
            sprintf('OpenAI %s request failed (HTTP %d). Nothing was generated.', $kind, $status)
        );
    }
}
