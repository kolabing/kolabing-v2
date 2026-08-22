<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EventSignup;

/**
 * The one place that decides what a ticket QR points at, and where a ticket lives.
 *
 * Two different people follow two different links, so there are two methods:
 *
 *  - {@see admitUrl()} is what the QR encodes. A doorkeeper's camera opens it, the
 *    panel confirms who they are and admits the holder. It has to be a URL a phone
 *    camera can simply open — not an API path and not a bare code, neither of which
 *    a camera can do anything with (the same mistake {@see CheckinLink} was written
 *    to fix).
 *  - {@see ticketUrl()} is what the confirmation email links to: the attendee's own
 *    ticket, where the QR is drawn.
 */
final class TicketLink
{
    /** What the QR encodes: the doorkeeper's admit page for this ticket. */
    public static function admitUrl(EventSignup $signup): string
    {
        return self::admitUrlForCode((string) $signup->ticket_code);
    }

    public static function admitUrlForCode(string $code): string
    {
        return rtrim((string) config('webapp.url'), '/').'/admit/'.$code;
    }

    /** Where the holder sees their own ticket. */
    public static function ticketUrl(EventSignup $signup): string
    {
        return rtrim((string) config('webapp.url'), '/').'/tickets?t='.$signup->ticket_code;
    }
}
