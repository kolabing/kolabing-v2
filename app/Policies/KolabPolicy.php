<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Kolab;
use App\Models\Profile;

class KolabPolicy
{
    /**
     * Determine whether the user can create kolabs.
     * Any authenticated user can create kolabs.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the kolab.
     * Any authenticated user can view published kolabs.
     * Creator can view kolabs in any status.
     */
    public function view(Profile $user, Kolab $kolab): bool
    {
        if ($this->isCreator($user, $kolab)) {
            return true;
        }

        return $kolab->isPublished();
    }

    /**
     * Determine whether the user can update the kolab.
     * Only creator can update.
     */
    public function update(Profile $user, Kolab $kolab): bool
    {
        return $this->isCreator($user, $kolab);
    }

    /**
     * Determine whether the user can delete the kolab.
     * Only creator can delete; must be draft.
     */
    public function delete(Profile $user, Kolab $kolab): bool
    {
        if (! $this->isCreator($user, $kolab)) {
            return false;
        }

        return $kolab->isDraft();
    }

    /**
     * Determine whether the user can publish the kolab.
     * Only creator; must be draft.
     */
    public function publish(Profile $user, Kolab $kolab): bool
    {
        if (! $this->isCreator($user, $kolab)) {
            return false;
        }

        return $kolab->isDraft();
    }

    /**
     * Determine whether the user can close the kolab.
     * Only creator; must be published.
     */
    public function close(Profile $user, Kolab $kolab): bool
    {
        if (! $this->isCreator($user, $kolab)) {
            return false;
        }

        return $kolab->isPublished();
    }

    /**
     * Check if the user is the creator of the kolab.
     */
    private function isCreator(Profile $user, Kolab $kolab): bool
    {
        return $user->id === $kolab->creator_profile_id;
    }
}
