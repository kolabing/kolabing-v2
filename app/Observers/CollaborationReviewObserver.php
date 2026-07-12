<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CollaborationReview;
use App\Services\ProfileService;

/**
 * Keeps the cached reputation summary fresh: any change to a review received
 * by a profile busts that profile's cached reputation. Fires on every write
 * path (API, admin moderation, seeders), so the cache can never go stale.
 */
class CollaborationReviewObserver
{
    public function created(CollaborationReview $collaborationReview): void
    {
        $this->forget($collaborationReview);
    }

    public function updated(CollaborationReview $collaborationReview): void
    {
        $this->forget($collaborationReview);
    }

    public function deleted(CollaborationReview $collaborationReview): void
    {
        $this->forget($collaborationReview);
    }

    private function forget(CollaborationReview $collaborationReview): void
    {
        if ($collaborationReview->reviewed_profile_id !== null) {
            ProfileService::forgetReputationSummary($collaborationReview->reviewed_profile_id);
        }
    }
}
