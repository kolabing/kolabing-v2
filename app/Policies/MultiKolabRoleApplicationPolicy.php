<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;

class MultiKolabRoleApplicationPolicy
{
    /**
     * Applying is always free — never gated by subscription or Event
     * Creator entitlement (Global Constraints). Cannot apply to your own
     * event's role.
     */
    public function create(Profile $user, MultiKolabRole $role): bool
    {
        $role->loadMissing('event');

        return $role->event !== null && $user->id !== $role->event->creator_profile_id;
    }

    /**
     * Only the applicant or the event's organizer may view an application.
     */
    public function view(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $this->isApplicant($user, $application) || $this->isOrganizer($user, $application);
    }

    /**
     * Only the event's organizer may shortlist.
     */
    public function shortlist(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $this->isOrganizer($user, $application);
    }

    /**
     * Only the event's organizer may decline.
     */
    public function decline(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $this->isOrganizer($user, $application);
    }

    /**
     * Only the applicant may withdraw their own application.
     */
    public function withdraw(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $this->isApplicant($user, $application);
    }

    /**
     * Only the event's organizer may accept (added Task 7 — `accept()` did
     * not exist on the service when this policy was first written in
     * Task 5).
     */
    public function accept(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $this->isOrganizer($user, $application);
    }

    /**
     * Only the event's organizer may list a role's applications for review.
     */
    public function viewAnyForRole(Profile $user, MultiKolabRole $role): bool
    {
        $role->loadMissing('event');

        return $role->event !== null && $user->id === $role->event->creator_profile_id;
    }

    private function isApplicant(Profile $user, MultiKolabRoleApplication $application): bool
    {
        return $user->id === $application->applicant_profile_id;
    }

    private function isOrganizer(Profile $user, MultiKolabRoleApplication $application): bool
    {
        $application->loadMissing('role.event');

        return $application->role?->event !== null
            && $user->id === $application->role->event->creator_profile_id;
    }
}
