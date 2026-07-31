<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the developer/moderation team when a user reports objectionable
 * content or blocks another user (App Review Guideline 1.2). Queued so the
 * report/block request returns immediately.
 */
class ModerationAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  'report'|'block'  $event
     * @param  array<string, string|null>  $details
     */
    public function __construct(
        public readonly string $event,
        public readonly string $reporterLabel,
        public readonly string $targetLabel,
        public readonly ?string $reason,
        public readonly ?string $contentSummary,
        public readonly array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->event === 'block'
            ? 'Kolabing moderation: user blocked'
            : 'Kolabing moderation: content reported';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.moderation-alert',
        );
    }
}
