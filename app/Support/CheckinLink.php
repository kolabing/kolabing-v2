<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;

/**
 * The one place that decides what a check-in QR points at.
 *
 * A QR has to carry a URL a phone camera can simply open — not an API path and not
 * a bare token, both of which the previous implementation produced and neither of
 * which a camera can do anything with. It points at the panel, which performs the
 * check-in after signing the attendee in if needed.
 *
 * The short code goes in the URL rather than the 64-character token, for a practical
 * reason: it keeps the code at version 3 (29×29 modules) instead of version 6
 * (41×41), which is the difference between scanning across a room and having to walk
 * up to the screen. The long token still works on the same route, so the mobile
 * client's existing flow is untouched.
 */
final class CheckinLink
{
    /** The absolute URL a QR should encode for an event. */
    public static function urlFor(Event $event): string
    {
        return self::urlForToken($event->checkin_code ?? (string) $event->checkin_token);
    }

    public static function urlForToken(string $token): string
    {
        return rtrim((string) config('webapp.url'), '/').'/checkin/'.$token;
    }
}
