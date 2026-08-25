<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Services\PostHog\PostHogService;
use App\Services\ProfileService;

/**
 * Busts both parties' cached reputation whenever a collaboration is created,
 * its status changes, or it is deleted. Completed-collaboration state feeds
 * both completed_kolabs_count and which reviews count toward reputation, so
 * either party's summary may change.
 */
class CollaborationObserver
{
    public function __construct(
        private readonly PostHogService $postHog,
    ) {}

    public function saved(Collaboration $collaboration): void
    {
        if ($collaboration->wasRecentlyCreated || $collaboration->wasChanged('status')) {
            $this->forgetBothParties($collaboration);
        }

        if ($collaboration->wasChanged('status') && $collaboration->status === CollaborationStatus::Completed) {
            $this->captureChildKolabCompleted($collaboration);
        }
    }

    /**
     * Multi-Kolab Event MVP analytics (Task 8): fires only for a
     * Collaboration whose Kolab was created by accepting a
     * MultiKolabRoleApplication — an ordinary Kolab's collaboration
     * completing is unaffected and emits nothing here.
     */
    private function captureChildKolabCompleted(Collaboration $collaboration): void
    {
        $kolab = $collaboration->kolab;

        if ($kolab === null || $kolab->multi_kolab_event_id === null) {
            return;
        }

        $this->postHog->capture($collaboration->creator_profile_id, 'child_kolab_completed', [
            'kolab_id' => $kolab->id,
            'multi_kolab_event_id' => $kolab->multi_kolab_event_id,
            'multi_kolab_role_id' => $kolab->multi_kolab_role_id,
            'collaboration_id' => $collaboration->id,
        ]);
    }

    public function deleted(Collaboration $collaboration): void
    {
        $this->forgetBothParties($collaboration);
    }

    private function forgetBothParties(Collaboration $collaboration): void
    {
        foreach ([$collaboration->creator_profile_id, $collaboration->applicant_profile_id] as $profileId) {
            if ($profileId !== null) {
                ProfileService::forgetReputationSummary($profileId);
            }
        }
    }
}
