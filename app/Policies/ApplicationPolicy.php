<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Profile;

class ApplicationPolicy
{
    /**
     * Determine whether the user can view any applications.
     * Any authenticated user can view their own applications.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the application.
     * Only applicant or opportunity creator can view.
     */
    public function view(Profile $user, Application $application): bool
    {
        return $this->isApplicant($user, $application)
            || $this->isOpportunityCreator($user, $application);
    }

    /**
     * Determine whether the user can create an application for the opportunity.
     * Cannot apply to own opportunity; opportunity must be published;
     * profile must be complete.
     */
    public function create(Profile $user, CollabOpportunity $opportunity): bool
    {
        // Cannot apply to own opportunity
        if ($user->id === $opportunity->creator_profile_id) {
            return false;
        }

        // Opportunity must be open for applications (published)
        if (! $opportunity->isOpenForApplications()) {
            return false;
        }

        // Profile must have completed onboarding
        if (! $user->onboarding_completed) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can accept the application.
     * Only opportunity creator; must be pending; business creators need active subscription.
     */
    public function accept(Profile $user, Application $application): bool
    {
        if (! $this->isOpportunityCreator($user, $application)) {
            return false;
        }

        if ($application->isAccepted() && $application->collaboration !== null) {
            return true;
        }

        if (! $application->canBeAccepted()) {
            return false;
        }

        // Business users need active subscription to accept applications
        if ($user->isBusiness() && ! $user->hasActiveSubscription()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can decline the application.
     * Only opportunity creator; must be pending.
     */
    public function decline(Profile $user, Application $application): bool
    {
        if (! $this->isOpportunityCreator($user, $application)) {
            return false;
        }

        return $application->canBeDeclined();
    }

    /**
     * Determine whether the user can withdraw the application.
     * Only applicant; must be pending.
     */
    public function withdraw(Profile $user, Application $application): bool
    {
        if (! $this->isApplicant($user, $application)) {
            return false;
        }

        return $application->canBeWithdrawn();
    }

    /**
     * Check if the user is the applicant.
     */
    private function isApplicant(Profile $user, Application $application): bool
    {
        return $user->id === $application->applicant_profile_id;
    }

    /**
     * Check if the user is the opportunity creator.
     */
    private function isOpportunityCreator(Profile $user, Application $application): bool
    {
        return $user->id === $application->collabOpportunity->creator_profile_id;
    }
}
