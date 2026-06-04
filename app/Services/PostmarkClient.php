<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client over the Postmark HTTP API.
 *
 * Copy/design for transactional emails lives in Postmark templates (by alias);
 * this client supplies the merge model. A raw (subject/body) path exists for
 * connectivity smoke tests before any template is published.
 *
 * This deliberately bypasses Laravel's `Mail` transport: we send by template
 * *alias*, which Symfony's postmark transport does not support.
 */
class PostmarkClient
{
    private const BASE_URL = 'https://api.postmarkapp.com';

    public function __construct(
        private readonly ?string $token,
        private readonly string $from,
        private readonly string $fromName,
        private readonly string $messageStream,
    ) {}

    /**
     * Send a Postmark template email.
     *
     * @param  array<string, mixed>  $model  Template merge variables.
     */
    public function sendTemplate(string $to, string $templateAlias, array $model, ?string $toName = null): void
    {
        $this->post('/email/withTemplate', [
            'From' => $this->fromHeader(),
            'To' => $this->formatRecipient($to, $toName),
            'TemplateAlias' => $templateAlias,
            'TemplateModel' => (object) $model,
            'MessageStream' => $this->messageStream,
        ]);
    }

    /**
     * Send a raw (non-template) email. Used for connectivity smoke tests.
     */
    public function sendRaw(string $to, string $subject, string $htmlBody, ?string $textBody = null, ?string $toName = null): void
    {
        $payload = [
            'From' => $this->fromHeader(),
            'To' => $this->formatRecipient($to, $toName),
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
            'MessageStream' => $this->messageStream,
        ];

        if ($textBody !== null) {
            $payload['TextBody'] = $textBody;
        }

        $this->post('/email', $payload);
    }

    /**
     * Map of existing template alias => Postmark TemplateId.
     *
     * @return array<string, int>
     */
    public function templateAliasMap(): array
    {
        $data = $this->requestJson('GET', '/templates?count=300&offset=0');
        $map = [];

        foreach (($data['Templates'] ?? []) as $template) {
            if (! empty($template['Alias'])) {
                $map[$template['Alias']] = (int) $template['TemplateId'];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTemplate(array $payload): void
    {
        $this->requestJson('POST', '/templates', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTemplate(int $templateId, array $payload): void
    {
        $this->requestJson('PUT', '/templates/'.$templateId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): void
    {
        $this->requestJson('POST', $path, $payload);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $payload = null): array
    {
        if (blank($this->token)) {
            throw new RuntimeException('POSTMARK_API_KEY is not configured; cannot reach Postmark.');
        }

        $request = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Postmark-Server-Token' => $this->token,
        ]);

        $url = self::BASE_URL.$path;

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $payload ?? []),
            'PUT' => $request->put($url, $payload ?? []),
            default => throw new RuntimeException('Unsupported Postmark HTTP method: '.$method),
        };

        if ($response->failed()) {
            Log::error('Postmark request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException(
                'Postmark request failed ('.$response->status().'): '.$response->body()
            );
        }

        return (array) $response->json();
    }

    private function fromHeader(): string
    {
        return $this->fromName !== ''
            ? sprintf('%s <%s>', $this->fromName, $this->from)
            : $this->from;
    }

    private function formatRecipient(string $email, ?string $name): string
    {
        return ($name !== null && $name !== '')
            ? sprintf('%s <%s>', $name, $email)
            : $email;
    }
}
