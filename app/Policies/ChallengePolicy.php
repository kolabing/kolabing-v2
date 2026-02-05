<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Challenge;
use App\Models\Event;
use App\Models\Profile;

class ChallengePolicy
{
    /**
     * Any authenticated user can view challenges.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user can view a single challenge.
     */
    public function view(Profile $user, Challenge $challenge): bool
    {
        return true;
    }

    /**
     * Only the event owner can create challenges for their event.
     */
    public function create(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }

    /**
     * Only the event owner can update non-system challenges.
     */
    public function update(Profile $user, Challenge $challenge): bool
    {
        return ! $challenge->is_system
            && $challenge->event !== null
            && $user->id === $challenge->event->profile_id;
    }

    /**
     * Only the event owner can delete non-system challenges.
     */
    public function delete(Profile $user, Challenge $challenge): bool
    {
        return ! $challenge->is_system
            && $challenge->event !== null
            && $user->id === $challenge->event->profile_id;
    }
}
