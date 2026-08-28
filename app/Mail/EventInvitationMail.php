<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A calendar invitation for one attendee (#252).
 *
 * The payload is the `.ics` attachment, not the body: Gmail, Apple Mail and
 * Outlook all read `METHOD:REQUEST` and render their own native invitation UI
 * with accept/decline. The body is what someone sees if their client does not.
 */
class EventInvitationMail extends Mailable implements ShouldQueue
{
    public function __construct(
        public readonly Event $event,
        public readonly ?string $recipientName,
        private readonly string $ics,
        private readonly string $method = 'REQUEST',
    ) {}

    /**
     * The ICS body. Exposed so tests can assert on it without unpacking MIME.
     */
    public function ics(): string
    {
        return $this->ics;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function envelope(): Envelope
    {
        $name = $this->event->name ?? 'your event';

        return new Envelope(
            subject: $this->method === 'CANCEL'
                ? "Cancelled: {$name}"
                : "Invitation: {$name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-invitation',
            with: [
                'event' => $this->event,
                'recipientName' => $this->recipientName,
                'cancelled' => $this->method === 'CANCEL',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->ics, 'invite.ics')
                ->withMime('text/calendar; method='.$this->method.'; charset=UTF-8'),
        ];
    }
}
