<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CollabOpportunity;
use App\Models\Profile;

class OpportunityPolicy
{
    /**
     * Determine whether the user can view any opportunities.
     * Any authenticated user can browse opportunities.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the opportunity.
     * Any authenticated user can view published opportunities.
     * Creator can view opportunities in any status.
     */
    public function view(Profile $user, CollabOpportunity $opportunity): bool
    {
        if ($this->isCreator($user, $opportunity)) {
            return true;
        }

        return $opportunity->isPublished();
    }

    /**
     * Determine whether the user can create opportunities.
     * Any authenticated user can create opportunities.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the opportunity.
     * Only creator can update; must be draft or published.
     */
    public function update(Profile $user, CollabOpportunity $opportunity): bool
    {
        if (! $this->isCreator($user, $opportunity)) {
            return false;
        }

        return $opportunity->isDraft() || $opportunity->isPublished();
    }

    /**
     * Determine whether the user can delete the opportunity.
     * Only creator can delete; must be draft with no applications.
     */
    public function delete(Profile $user, CollabOpportunity $opportunity): bool
    {
        if (! $this->isCreator($user, $opportunity)) {
            return false;
        }

        if (! $opportunity->isDraft()) {
            return false;
        }

        return $opportunity->applications()->count() === 0;
    }

    /**
     * Determine whether the user can publish the opportunity.
     * Only creator; must be draft.
     */
    public function publish(Profile $user, CollabOpportunity $opportunity): bool
    {
        if (! $this->isCreator($user, $opportunity)) {
            return false;
        }

        if (! $opportunity->isDraft()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can close the opportunity.
     * Only creator; must be published.
     */
    public function close(Profile $user, CollabOpportunity $opportunity): bool
    {
        if (! $this->isCreator($user, $opportunity)) {
            return false;
        }

        return $opportunity->isPublished();
    }

    /**
     * Determine whether the user can view applications for the opportunity.
     * Only creator can view applications.
     */
    public function viewApplications(Profile $user, CollabOpportunity $opportunity): bool
    {
        return $this->isCreator($user, $opportunity);
    }

    /**
     * Check if the user is the creator of the opportunity.
     */
    private function isCreator(Profile $user, CollabOpportunity $opportunity): bool
    {
        return $user->id === $opportunity->creator_profile_id;
    }
}
