<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KolabSuggestion;
use App\Models\Profile;

/**
 * IDOR guard for `kolab_suggestions`.
 *
 * A suggestion is not merely a row about the viewer — it names *who the viewer
 * was matched with*, plus a score and the reasoning behind it. Serving one to
 * anybody but `viewer_profile_id` would leak the pairing itself, so ownership is
 * the only rule here and it is the same rule for reading and for dismissing.
 *
 * Auto-discovered by Laravel 12 (App\Models\KolabSuggestion →
 * App\Policies\KolabSuggestionPolicy); the forbidden-access tests in
 * SuggestionApiTest are what prove the discovery actually happened.
 */
class KolabSuggestionPolicy
{
    public function view(Profile $profile, KolabSuggestion $suggestion): bool
    {
        return $profile->id === $suggestion->viewer_profile_id;
    }

    /**
     * Dismissal is a write, but it is not a *stronger* right than reading: the
     * viewer is retiring a card addressed to them. Deliberately delegates rather
     * than repeating the comparison, so the two can never drift apart.
     */
    public function dismiss(Profile $profile, KolabSuggestion $suggestion): bool
    {
        return $this->view($profile, $suggestion);
    }
}
