<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/**
 * Hides rows whose owning `profiles` row has been switched off (#254, #258).
 *
 * #254 scoped the sub-profiles and stopped there, which hid the *profile* but
 * not the things a profile owns. A deactivated community still came back from
 * `/communities/discover` — and in the worst possible shape, because the
 * `Community` row survived while its eager-loaded `communityProfile` was scoped
 * away, so it rendered as a nameless card instead of not rendering at all.
 *
 * Parameterised by the owner column because every table names it differently
 * (`owner_profile_id`, `creator_profile_id`, `profile_id`). `whereExists` rather
 * than `whereHas`: it does not depend on a relation name existing on the model,
 * and it stays a single subquery.
 *
 * Every owner column this is applied to is NOT NULL, so there is no orphan case
 * to preserve. If that ever changes, this scope starts silently hiding rows with
 * a null owner and needs an explicit `orWhereNull`.
 *
 * NOT applied to Profile itself — see ActiveProfileScope for why.
 */
class ActiveOwnerScope implements Scope
{
    public function __construct(private readonly string $ownerKey) {}

    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();
        $ownerKey = $this->ownerKey;

        $builder->whereExists(function ($query) use ($table, $ownerKey): void {
            $query->select(DB::raw(1))
                ->from('profiles')
                ->whereColumn('profiles.id', "{$table}.{$ownerKey}")
                ->where('profiles.is_active', true);
        });
    }
}
