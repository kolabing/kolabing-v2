<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventSignupStatus;
use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Calendar invitations as ICS over email (kolabing-app#191, #252).
 *
 * Deliberately not the Google Calendar API. A `METHOD:REQUEST` attachment is a
 * real invitation in Gmail, Apple Mail and Outlook alike, works for every
 * attendee regardless of how they signed in, and needs no OAuth and no
 * sensitive-scope review.
 *
 * Its honest ceiling: whether an invitation lands in the calendar automatically
 * or only after the recipient accepts is a user-side setting in Gmail. No
 * non-OAuth approach can do better, so nothing downstream should promise silent
 * insertion.
 */
class CalendarInvitationService
{
    private const METHOD_REQUEST = 'REQUEST';

    private const METHOD_CANCEL = 'CANCEL';

    /**
     * Default length when an event has no `ends_at`. A knob, not a rule — but a
     * calendar entry with no end is worse than one with a plausible guess.
     */
    private const DEFAULT_DURATION_HOURS = 2;

    /**
     * Invite one attendee, on sign-up.
     */
    public function invite(EventSignup $signup): void
    {
        $signup->loadMissing(['event', 'profile']);

        if ($signup->status !== EventSignupStatus::Going) {
            // A waitlisted row holds no seat; a calendar entry that may not be
            // honoured is worse than none. Issued on promotion instead.
            return;
        }

        $this->send($signup->event, $signup->profile, self::METHOD_REQUEST);
    }

    /**
     * Re-issue to everyone going, after the event's time or place moved.
     *
     * Bumps `ics_sequence` once for the whole fan-out, so every attendee's
     * calendar receives the same revision of the same `UID` and updates the entry
     * in place rather than growing a second one.
     */
    public function reissueForEvent(Event $event): void
    {
        $event->increment('ics_sequence');
        $event->refresh();

        $this->eachGoing($event, fn (Profile $profile) => $this->send($event, $profile, self::METHOD_REQUEST));
    }

    /**
     * Withdraw the event from every attendee's calendar.
     */
    public function cancelForEvent(Event $event): void
    {
        $event->increment('ics_sequence');
        $event->refresh();

        $this->eachGoing($event, fn (Profile $profile) => $this->send($event, $profile, self::METHOD_CANCEL));
    }

    /**
     * Withdraw the event from ONE attendee's calendar, when they leave.
     *
     * No `ics_sequence` bump: the event itself did not change, and bumping would
     * make everyone else's calendar think it had.
     */
    public function cancelForAttendee(Event $event, Profile $profile): void
    {
        $this->send($event, $profile, self::METHOD_CANCEL);
    }

    /**
     * The ICS body for one attendee. Public so it can be asserted directly.
     */
    public function build(Event $event, Profile $profile, string $method = self::METHOD_REQUEST): string
    {
        $startsAt = $event->starts_at ?? Carbon::parse($event->event_date);
        $endsAt = $event->ends_at ?? $startsAt->copy()->addHours(self::DEFAULT_DURATION_HOURS);

        $organiserName = $event->partner_name ?? config('app.name');
        $organiserAddress = config('mail.from.address');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kolabing//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            // Stable per EVENT, never per attendee: it is the same event in
            // everyone's calendar, which is what lets an update land in place.
            'UID:event-'.$event->id.'@kolabing.com',
            'SEQUENCE:'.(int) ($event->ics_sequence ?? 0),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$startsAt->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$endsAt->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($event->name ?? ''),
            'DESCRIPTION:'.$this->escape($this->description($event, $organiserName)),
            'ORGANIZER;CN='.$this->escape($organiserName).':mailto:'.$organiserAddress,
            'ATTENDEE;CN='.$this->escape($profile->name ?? '').';RSVP=TRUE:mailto:'.$profile->email,
            'STATUS:'.($method === self::METHOD_CANCEL ? 'CANCELLED' : 'CONFIRMED'),
        ];

        $location = $this->location($event);
        if ($location !== '') {
            array_splice($lines, -1, 0, ['LOCATION:'.$this->escape($location)]);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines))."\r\n";
    }

    private function send(?Event $event, ?Profile $profile, string $method): void
    {
        if ($event === null || $profile === null) {
            return;
        }

        $email = $profile->email;

        if ($email === null || trim($email) === '') {
            // Defensive: `profiles.email` is NOT NULL, so in practice only a
            // blank string gets here. Skipped rather than thrown either way —
            // a sign-up must never fail because of a calendar side-effect.
            Log::info('Calendar invitation skipped: no email on file', [
                'event_id' => $event->id,
                'profile_id' => $profile->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->queue(new EventInvitationMail(
                event: $event,
                recipientName: $profile->name,
                ics: $this->build($event, $profile, $method),
                method: $method,
            ));
        } catch (\Throwable $e) {
            // Same isolation as the notification email side-effect: a mail
            // failure must not roll back a sign-up someone already made.
            Log::warning('Calendar invitation failed', [
                'event_id' => $event->id,
                'profile_id' => $profile->id,
                'method' => $method,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  callable(Profile): void  $callback
     */
    private function eachGoing(Event $event, callable $callback): void
    {
        EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Going->value)
            ->with('profile')
            ->cursor()
            ->each(function (EventSignup $signup) use ($callback): void {
                if ($signup->profile !== null) {
                    $callback($signup->profile);
                }
            });
    }

    private function location(Event $event): string
    {
        return collect([$event->location, $event->address, $event->city?->name])
            ->filter(fn (?string $part): bool => $part !== null && trim($part) !== '')
            ->unique()
            ->implode(', ');
    }

    private function description(Event $event, string $organiserName): string
    {
        return collect([
            $organiserName,
            rtrim((string) config('app.url'), '/').'/events/'.$event->id,
        ])->filter()->implode("\n");
    }

    /**
     * RFC 5545 §3.3.11 — backslash, semicolon, comma and newline are structural
     * in a content line and have to be escaped or they truncate the field.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value
        );
    }

    /**
     * RFC 5545 §3.1 — a content line is at most 75 octets; longer ones are
     * folded onto continuation lines that begin with a single space. Gmail is
     * forgiving about this, Outlook is not.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = substr($line, 0, 75);
        $rest = substr($line, 75);

        while (strlen($rest) > 74) {
            $folded .= "\r\n ".substr($rest, 0, 74);
            $rest = substr($rest, 74);
        }

        return $rest === '' ? $folded : $folded."\r\n ".$rest;
    }
}
