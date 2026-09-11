<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventSignupStatus;
use App\Mail\EventTicketMail;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSignup;
use App\Models\Profile;
use App\Support\QrCode;
use App\Support\TicketLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use LogicException;

/**
 * Tickets: issue one for a seat, draw it, email it, and let a host admit it.
 *
 * A ticket is deliberately thin — it is the sign-up, with a code. There is no
 * separate tickets table, because a second row that can disagree with
 * `event_signups.status` about whether someone has a seat is a bug waiting to
 * happen: cancel a sign-up and a stale ticket would still scan.
 *
 * Admission is recorded in `event_checkins`, the same table the host-displayed door
 * QR writes to, so an event's attendance is one number however people got in.
 */
class TicketService
{
    /**
     * Unambiguous alphabet: no O/0, no I/1/L. A doorkeeper reads these aloud and
     * types them in bad light, and a ticket that cannot be typed is a ticket that
     * fails exactly when the QR already failed.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 10;

    public function __construct(private readonly CheckinService $checkinService) {}

    /**
     * Give this seat a ticket, or return the one it already has.
     *
     * Idempotent on purpose. Re-issuing would change the code already sitting in
     * someone's inbox, and "my ticket stopped working" is worse than any reason we
     * might have for minting a fresh one.
     */
    public function issue(EventSignup $signup): EventSignup
    {
        if ($signup->status !== EventSignupStatus::Going) {
            throw new LogicException('Only a confirmed seat can hold a ticket.');
        }

        if ($signup->ticket_code !== null) {
            return $signup;
        }

        $signup->update([
            'ticket_code' => $this->uniqueCode(),
            'ticket_issued_at' => now(),
        ]);

        return $signup->refresh();
    }

    /**
     * Issue and email in one step, which is what signing up should do.
     *
     * A failed send must not undo a sign-up: the seat is real, the ticket is real,
     * and the attendee can always open it in the app. So the mail is queued and any
     * failure is logged rather than thrown.
     */
    public function issueAndSend(EventSignup $signup): EventSignup
    {
        $signup = $this->issue($signup);

        $this->send($signup);

        return $signup;
    }

    /**
     * Email the ticket. Safe to call again — `ticket_emailed_at` records the last
     * successful hand-off to the mailer, not a promise that it will not be resent.
     */
    public function send(EventSignup $signup): void
    {
        $signup->loadMissing(['event', 'profile']);

        $address = $signup->profile?->email;

        if ($address === null || $address === '') {
            return;
        }

        try {
            Mail::to($address)->send(new EventTicketMail($signup));
            $signup->update(['ticket_emailed_at' => now()]);
        } catch (\Throwable $exception) {
            // The seat is booked and the ticket exists in the app either way.
            Log::warning('Ticket email failed', [
                'event_signup_id' => $signup->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /** The ticket QR, as an inline SVG. No image files, no external service. */
    public function qrSvg(EventSignup $signup): string
    {
        if ($signup->ticket_code === null) {
            throw new LogicException('This sign-up has no ticket to draw.');
        }

        return QrCode::svg(TicketLink::admitUrl($signup));
    }

    /**
     * Let the holder of this code in.
     *
     * `$doorkeeper` is the person scanning, not the person being admitted — that is
     * the whole difference from {@see CheckinService::checkin()}, where the attendee
     * scans the host's code. Authorisation is therefore about the scanner: only
     * someone who hosts the event may admit anyone to it.
     *
     * @throws InvalidArgumentException unknown code
     * @throws LogicException not the host | seat cancelled | already admitted
     */
    public function admit(string $code, Profile $doorkeeper): EventCheckin
    {
        $signup = $this->findByCode($code);

        if ($signup === null) {
            throw new InvalidArgumentException('That ticket does not exist.');
        }

        $event = $signup->event;

        if ($event === null) {
            throw new InvalidArgumentException('That ticket does not exist.');
        }

        if (! $event->isHostedBy($doorkeeper)) {
            throw new LogicException('Only the host of this event can admit people to it.');
        }

        if ($signup->status !== EventSignupStatus::Going) {
            throw new LogicException('This ticket was cancelled.');
        }

        $holder = $signup->profile;

        if ($holder === null) {
            throw new InvalidArgumentException('That ticket does not exist.');
        }

        return $this->checkinService->record($event, $holder);
    }

    /** Look up a ticket by its code, case-insensitively — doorkeepers type in caps. */
    public function findByCode(string $code): ?EventSignup
    {
        $normalised = strtoupper(trim($code));

        if ($normalised === '') {
            return null;
        }

        return EventSignup::query()
            ->where('ticket_code', $normalised)
            ->with(['event', 'profile'])
            ->first();
    }

    /**
     * The holder's own tickets, soonest happening first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, EventSignup>
     */
    public function forProfile(Profile $profile, bool $upcomingOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        /*
         * Joined rather than `whereHas`, because the ordering needs the event's own
         * columns: a wallet is only useful in the order you will need the tickets.
         * Every column is table-qualified — `event_signups` and `events` both have a
         * `profile_id` and a `status`, so an unqualified `where` is ambiguous and
         * fails outright on SQLite and Postgres alike.
         */
        $query = EventSignup::query()
            ->select('event_signups.*')
            ->join('events', 'events.id', '=', 'event_signups.event_id')
            ->where('event_signups.profile_id', $profile->id)
            ->where('event_signups.status', EventSignupStatus::Going->value)
            ->whereNotNull('event_signups.ticket_code')
            ->with(['event.profile']);

        if ($upcomingOnly) {
            $query->where(fn ($window) => $window
                ->where('events.starts_at', '>=', now())
                ->orWhere('events.event_date', '>=', now()->toDateString()));
        }

        return $query
            ->orderByRaw('COALESCE(events.starts_at, events.event_date) asc')
            ->get();
    }

    private function uniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (EventSignup::query()->where('ticket_code', $code)->exists());

        return $code;
    }
}
