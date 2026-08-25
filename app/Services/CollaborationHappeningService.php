<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\EventVisibility;
use App\Enums\UserType;
use App\Models\Collaboration;
use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * The happening: the thing an attendee actually turns up to.
 *
 * There is no separate events product. A Kolab is agreed, that agreement becomes a
 * `collaborations` row, and *that* is the thing with a date, a place and a door. This
 * service is the one place that turns the agreement into something the public can see
 * and join, and it writes an `events` row to do it — `events` being the internal
 * representation of a happening (it already carries the date, the capacity, the
 * check-in token and the photos), not a product of its own.
 *
 * Deliberately idempotent and re-runnable. A collaboration's date can change after
 * the fact, so this updates in place; it must never mint a second happening for the
 * same collaboration, because the door token and everyone's tickets hang off the
 * first one.
 */
class CollaborationHappeningService
{
    /** How long a happening is assumed to run when nobody has said. */
    private const DEFAULT_DURATION_HOURS = 3;

    /**
     * Create or refresh the happening for a collaboration.
     *
     * Always returns an Event unless the collaboration is cancelled, because the
     * *door* needs one whether or not a date has been agreed — a host may want to
     * check people in today for something arranged by message. What the date decides
     * is whether the happening is **public**: no agreed date means nobody can be told
     * when to turn up, so it stays `members` and never reaches "what's on".
     */
    public function ensureFor(Collaboration $collaboration): ?Event
    {
        $collaboration->loadMissing(['kolab', 'creatorProfile', 'applicantProfile', 'event']);

        if ($collaboration->status === CollaborationStatus::Cancelled) {
            return null;
        }

        $startsAt = $this->startsAt($collaboration);
        $kolab = $collaboration->kolab;

        $attributes = [
            'name' => $kolab?->title ?? __('Kolabing collaboration'),
            'partner_name' => $this->partnerName($collaboration),
            'partner_type' => $collaboration->applicantProfile?->user_type?->value ?? 'community',
            'event_date' => ($startsAt ?? now())->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt?->copy()->addHours(self::DEFAULT_DURATION_HOURS),
            'address' => $kolab?->venue_address,
            'location' => $kolab?->preferred_city,
            'capacity' => $kolab?->capacity,
            /*
             * Public when there is a date, and that is the point: a confirmed Kolab
             * exists to be attended — the business wants footfall, the community wants
             * its people there. The `members` default belongs to a community's own
             * private calendar, and it was also what made these happenings unjoinable:
             * EventSignupService used to refuse anything without a `community_id`,
             * which a collaboration has no reliable way to have (its community side is
             * a *profile*; the column points at the NF-6 `communities` table).
             */
            'visibility' => $startsAt !== null
                ? EventVisibility::Public->value
                : EventVisibility::Members->value,
            'collaboration_id' => $collaboration->id,
        ];

        $event = $collaboration->event;

        if ($event !== null) {
            $event->update($attributes);

            return $event->refresh();
        }

        $event = Event::query()->create([
            'profile_id' => $collaboration->creator_profile_id,
            ...$attributes,
        ]);

        // Both directions of the link, because both are read: the collaboration page
        // asks "where is my happening", the door asks "whose collaboration is this".
        $collaboration->update(['event_id' => $event->id]);

        return $event;
    }

    /**
     * Who the host is collaborating with, in words.
     *
     * Not `$profile->display_name` — that attribute does not exist on Profile, so the
     * previous inline version of this (in CollaborationQrCodeController) always
     * resolved to null and every generated event was hosted with "Partner". The name
     * lives on the extended profile for a business or a community and on `profiles`
     * itself for an attendee.
     */
    private function partnerName(Collaboration $collaboration): string
    {
        $partner = $collaboration->applicantProfile;

        $name = match ($partner?->user_type) {
            UserType::Business => $partner->businessProfile?->name,
            UserType::Community => $partner->communityProfile?->name,
            UserType::Attendee => $partner->name,
            default => null,
        };

        return $name ?: __('Partner');
    }

    /**
     * When the happening starts.
     *
     * `collaborations.scheduled_date` is a date, not a datetime — the time of day
     * lives on the Kolab as free text (`selected_time`, e.g. "19:00 – 21:00"), which
     * is what the two sides actually agreed in the application. Parse the first clock
     * time out of it and fall back to a civilised evening hour rather than midnight,
     * because "Sunday at 00:00" reads as missing data.
     */
    private function startsAt(Collaboration $collaboration): ?Carbon
    {
        $date = $collaboration->scheduled_date;

        if ($date === null) {
            return null;
        }

        $time = (string) ($collaboration->kolab?->selected_time ?? '');

        if (preg_match('/(\d{1,2})[:.](\d{2})/', $time, $matches) === 1) {
            $hour = min(23, (int) $matches[1]);
            $minute = min(59, (int) $matches[2]);

            return $date->copy()->setTime($hour, $minute);
        }

        return $date->copy()->setTime(19, 0);
    }
}
