<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Collaboration;
use App\Models\Profile;

class CollaborationPolicy
{
    /**
     * Determine whether the user can view any collaborations.
     * Any authenticated user can view their own collaborations.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the collaboration.
     * Only participants (creator or applicant) can view.
     */
    public function view(Profile $user, Collaboration $collaboration): bool
    {
        return $this->isParticipant($user, $collaboration);
    }

    /**
     * Determine whether the user can activate the collaboration.
     * Only participants; must be scheduled.
     */
    public function activate(Profile $user, Collaboration $collaboration): bool
    {
        if (! $this->isParticipant($user, $collaboration)) {
            return false;
        }

        return $collaboration->canBeActivated();
    }

    /**
     * Determine whether the user can complete the collaboration.
     * Only participants; must be scheduled or active.
     */
    public function complete(Profile $user, Collaboration $collaboration): bool
    {
        if (! $this->isParticipant($user, $collaboration)) {
            return false;
        }

        // Can complete from scheduled or active status
        return $collaboration->isScheduled() || $collaboration->isActive();
    }

    /**
     * Determine whether the user can cancel the collaboration.
     * Only participants; cannot be completed.
     */
    public function cancel(Profile $user, Collaboration $collaboration): bool
    {
        if (! $this->isParticipant($user, $collaboration)) {
            return false;
        }

        return $collaboration->canBeCancelled();
    }

    /**
     * Check if the user is a participant in the collaboration.
     * A participant is either the opportunity creator or the applicant.
     */
    private function isParticipant(Profile $user, Collaboration $collaboration): bool
    {
        return $user->id === $collaboration->creator_profile_id
            || $user->id === $collaboration->applicant_profile_id;
    }
}
