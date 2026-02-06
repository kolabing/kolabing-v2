<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CheckinService
{
    public function __construct(
        private readonly BadgeService $badgeService
    ) {}

    /**
     * Generate a unique QR check-in token for an event.
     */
    public function generateCheckinToken(Event $event): string
    {
        $token = Str::random(64);
        $event->update([
            'checkin_token' => $token,
            'is_active' => true,
        ]);

        return $token;
    }

    /**
     * Check in an attendee using a QR token.
     */
    public function checkin(Profile $profile, string $token): EventCheckin
    {
        $event = Event::query()->where('checkin_token', $token)->first();

        if (! $event) {
            throw new \InvalidArgumentException('Invalid check-in token.');
        }

        if (! $event->is_active) {
            throw new \LogicException('This event is not currently accepting check-ins.');
        }

        $existing = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->exists();

        if ($existing) {
            throw new \LogicException('You have already checked in to this event.');
        }

        $checkin = EventCheckin::query()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'checked_in_at' => now(),
        ]);

        // Increment total_events_attended on attendee profile
        if ($profile->isAttendee() && $profile->attendeeProfile) {
            $profile->attendeeProfile->increment('total_events_attended');
            $profile->attendeeProfile->refresh();
            $this->badgeService->checkAndAwardBadges($profile);
        }

        return $checkin->load(['event', 'profile']);
    }

    /**
     * List check-ins for an event.
     */
    public function getCheckins(Event $event, int $perPage = 10): LengthAwarePaginator
    {
        return EventCheckin::query()
            ->where('event_id', $event->id)
            ->with(['profile'])
            ->orderByDesc('checked_in_at')
            ->paginate($perPage);
    }
}
