<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PostmarkClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;

/**
 * Queued delivery of a single transactional email via {@see PostmarkClient}.
 *
 * Mirrors {@see SendPushNotification}: same retry/backoff and the same
 * database-on-sync fallback. Email failures are isolated here and must never
 * propagate into the request that triggered the email.
 */
class SendTransactionalEmail implements ShouldQueue
{
    use Queueable;

    /** @var int Maximum number of retry attempts */
    public int $tries = 3;

    /** @var int Seconds to wait before retrying */
    public int $backoff = 10;

    /**
     * @param  'template'|'raw'  $mode
     * @param  array<string, mixed>  $data  template: {alias, model}; raw: {subject, html, text}
     */
    private function __construct(
        public readonly string $mode,
        public readonly string $to,
        public readonly ?string $toName,
        public readonly array $data,
    ) {
        if (Config::get('queue.default') === 'sync') {
            $this->onConnection('database');
        }
    }

    /**
     * Build a template-based email job.
     *
     * @param  array<string, mixed>  $model
     */
    public static function template(string $to, string $templateAlias, array $model, ?string $toName = null): self
    {
        return new self('template', $to, $toName, [
            'alias' => $templateAlias,
            'model' => $model,
        ]);
    }

    /**
     * Build a raw (non-template) email job. Used for connectivity smoke tests.
     */
    public static function raw(string $to, string $subject, string $htmlBody, ?string $textBody = null, ?string $toName = null): self
    {
        return new self('raw', $to, $toName, [
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ]);
    }

    public function handle(PostmarkClient $postmark): void
    {
        if ($this->mode === 'template') {
            $postmark->sendTemplate(
                to: $this->to,
                templateAlias: $this->data['alias'],
                model: $this->data['model'],
                toName: $this->toName,
            );

            return;
        }

        $postmark->sendRaw(
            to: $this->to,
            subject: $this->data['subject'],
            htmlBody: $this->data['html'],
            textBody: $this->data['text'] ?? null,
            toName: $this->toName,
        );
    }
}
