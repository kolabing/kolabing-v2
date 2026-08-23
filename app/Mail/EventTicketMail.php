<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EventSignup;
use App\Support\TicketLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You're in" — the ticket for a seat someone just took.
 *
 * Deliberately does NOT embed the QR. Every reliable way to put a QR in an email is
 * a raster attachment, and the encoder this app carries draws SVG, which Gmail and
 * Outlook both strip. So the email carries the two things that always survive — the
 * ticket code as text, and a link to the ticket — and the QR is drawn on the ticket
 * page, which is where a phone will be held up at the door anyway.
 *
 * Queued, so a slow or unreachable mailer never blocks a sign-up.
 */
class EventTicketMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly EventSignup $signup) {}

    public function envelope(): Envelope
    {
        $this->signup->loadMissing('event');

        return new Envelope(
            subject: __('You\'re in: :event', [
                'event' => $this->signup->event?->name ?? __('your Kolabing event'),
            ]),
        );
    }

    public function content(): Content
    {
        $this->signup->loadMissing(['event', 'profile']);

        $event = $this->signup->event;

        return new Content(
            markdown: 'mail.event-ticket',
            with: [
                'eventName' => $event?->name ?? __('your Kolabing event'),
                'hostName' => $event?->partner_name,
                'startsAt' => $event?->starts_at ?? $event?->event_date,
                'where' => $event?->address ?: $event?->location,
                'ticketCode' => (string) $this->signup->ticket_code,
                'ticketUrl' => TicketLink::ticketUrl($this->signup),
            ],
        );
    }
}
