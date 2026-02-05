<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Profile;

class EventPolicy
{
    /**
     * Any authenticated user can view events (public profiles).
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user can view a single event.
     */
    public function view(Profile $user, Event $event): bool
    {
        return true;
    }

    /**
     * Any authenticated user can create events.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Only the event owner can update.
     */
    public function update(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }

    /**
     * Only the event owner can delete.
     */
    public function delete(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }
}
