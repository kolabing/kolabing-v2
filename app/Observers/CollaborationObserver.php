<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Collaboration;
use App\Services\ProfileService;

/**
 * Busts both parties' cached reputation whenever a collaboration is created,
 * its status changes, or it is deleted. Completed-collaboration state feeds
 * both completed_kolabs_count and which reviews count toward reputation, so
 * either party's summary may change.
 */
class CollaborationObserver
{
    public function saved(Collaboration $collaboration): void
    {
        if ($collaboration->wasRecentlyCreated || $collaboration->wasChanged('status')) {
            $this->forgetBothParties($collaboration);
        }
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
