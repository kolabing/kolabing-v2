<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Api\V1\EventCheckinResource;
use App\Models\EventCheckin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone walked in.
 *
 * A door is watched from more than one screen at once — a laptop at the entrance,
 * the host's phone, web and mobile — and polling makes those screens disagree for a
 * few seconds after every arrival. This keeps them in step.
 *
 * Deliberately host-scoped: the channel carries who arrived, which is nobody else's
 * business. `routes/channels.php` authorises on event ownership, the same rule the
 * REST layer uses to decide who may see the check-in list at all.
 */
class AttendeeCheckedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EventCheckin $checkin) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.'.$this->checkin->event_id.'.door')];
    }

    public function broadcastAs(): string
    {
        return 'checkin.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'checkin' => (new EventCheckinResource($this->checkin))->resolve(),
            // Sent alongside so a screen that missed an earlier event still lands on
            // the right total instead of counting what it happened to receive.
            'checked_in_count' => EventCheckin::query()->where('event_id', $this->checkin->event_id)->count(),
        ];
    }
}
