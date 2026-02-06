<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;

class EventRewardPolicy
{
    /**
     * Any authenticated user can view rewards for an event.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Only the event owner can create rewards for their event.
     */
    public function create(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }

    /**
     * Only the event owner can update a reward.
     */
    public function update(Profile $user, EventReward $reward): bool
    {
        return $reward->event !== null
            && $user->id === $reward->event->profile_id;
    }

    /**
     * Only the event owner can delete a reward.
     */
    public function delete(Profile $user, EventReward $reward): bool
    {
        return $reward->event !== null
            && $user->id === $reward->event->profile_id;
    }
}
