<?php

declare(strict_types=1);

namespace App\Services\OpenAi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
        return $this->imageWithReferences($prompt, [], $size);
    }

    /**
     * Generate an image, optionally grounded in reference photographs.
     *
     * With references this posts to `/images/edits`, which accepts the source images
     * as multipart and lets the model take palette, materials and the look of the
     * real place from them — the difference between a generic stock room and
     * something that resembles the business being pitched. Without them it falls
     * back to plain `/images/generations`.
     *
     * A reference that cannot be fetched is skipped rather than fatal: a cover
     * grounded in two photos instead of three is still a cover, whereas failing the
     * whole pitch because one CDN was slow is not a trade worth making.
     *
     * @param  list<string>  $referenceUrls
     *
     * @throws RuntimeException
     */
    public function imageWithReferences(string $prompt, array $referenceUrls, string $size = '1536x1024'): string
    {
        $references = $this->fetchReferences($referenceUrls);

        if ($references === []) {
            return $this->generateImage($prompt, $size);
        }

        $request = $this->request(config('services.openai.image_timeout'));

        foreach ($references as $index => $bytes) {
            $request = $request->attach('image[]', $bytes, 'reference-'.$index.'.png');
        }

        $response = $request->post('/images/edits', [
            'model' => config('services.openai.image_model'),
            'prompt' => $prompt,
            'size' => $size,
        ]);

        if (! $response->successful()) {
            $this->fail('image', $response->status(), $response->body());
        }

        return $this->b64FromImageResponse($response);
    }

    /**
     * @throws RuntimeException
     */
    private function generateImage(string $prompt, string $size): string
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

        return $this->b64FromImageResponse($response);
    }

    /**
     * @throws RuntimeException
     */
    private function b64FromImageResponse(Response $response): string
    {
        $b64 = $response->json('data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            $this->fail('image', $response->status(), 'the response carried no image data');
        }

        // A data URI, not bare base64: FileUploadService reads the MIME type from
        // the prefix to pick the extension.
        return 'data:image/png;base64,'.$b64;
    }

    /**
     * Download the reference photographs, skipping any that will not come.
     *
     * Short timeout on purpose — these are a nice-to-have on a call that is already
     * slow, and one unreachable CDN must not be what makes a pitch fail.
     *
     * @param  list<string>  $urls
     * @return list<string> raw image bytes
     */
    private function fetchReferences(array $urls): array
    {
        $images = [];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(15)->get($url);

                if ($response->successful() && $response->body() !== '') {
                    $images[] = $response->body();
                }
            } catch (Throwable $e) {
                Log::info('Skipped an unreachable cover reference image', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $images;
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
