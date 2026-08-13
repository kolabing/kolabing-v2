<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MultiKolabEvent;
use App\Models\Profile;

class MultiKolabEventPolicy
{
    /**
     * Any authenticated Business or Community profile may start a draft.
     * Entitlement is only required to publish (see {@see publish()}) —
     * drafting must never be gated, per the plan's Global Constraints.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Any authenticated profile may view a recruiting/confirmed/completed
     * event; only the creator may view it in draft/cancelled state.
     */
    public function view(Profile $user, MultiKolabEvent $event): bool
    {
        if ($this->isCreator($user, $event)) {
            return true;
        }

        return in_array($event->status->value, ['recruiting', 'confirmed', 'completed'], true);
    }

    /**
     * Editing the event's own fields (title, description, roles, ...) —
     * owner-only.
     */
    public function update(Profile $user, MultiKolabEvent $event): bool
    {
        return $this->isCreator($user, $event);
    }

    /**
     * Publishing additionally requires the maintainer-granted Event Creator
     * capability — never {@see Profile::hasActiveSubscription()}.
     */
    public function publish(Profile $user, MultiKolabEvent $event): bool
    {
        return $this->update($user, $event) && $user->hasEventCreatorEntitlement();
    }

    public function confirm(Profile $user, MultiKolabEvent $event): bool
    {
        return $this->isCreator($user, $event);
    }

    public function complete(Profile $user, MultiKolabEvent $event): bool
    {
        return $this->isCreator($user, $event);
    }

    public function cancel(Profile $user, MultiKolabEvent $event): bool
    {
        return $this->isCreator($user, $event);
    }

    private function isCreator(Profile $user, MultiKolabEvent $event): bool
    {
        return $user->id === $event->creator_profile_id;
    }
}
