<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides sub-profiles whose owning `profiles` row has been switched off (#254).
 *
 * Applied to BusinessProfile / CommunityProfile / AttendeeProfile — deliberately
 * NOT to Profile itself. Profile is the Authenticatable: a global scope there
 * would also hide the row from Sanctum's token resolution, from route-model
 * binding and from the login lookup, turning a diagnosable "your account is
 * deactivated" into a bare 401 and locking the admin panel out of the very row
 * it needs to switch back on. Profile is guarded by EnsureProfileActive
 * middleware plus the explicit checks in AuthService instead.
 *
 * The sub-profiles are what every public read surface actually queries —
 * discovery, Explore, member lists, leaderboards — so scoping them is what makes
 * a deactivated account disappear from the app.
 *
 * Escape hatch: `BusinessProfile::withInactiveProfiles()` (see
 * HasActiveProfileScope), used by the admin panel and nowhere else.
 */
class ActiveProfileScope implements Scope
{
    public const IDENTIFIER = 'active_profile';

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('profile', function (Builder $query): void {
            $query->where('profiles.is_active', true);
        });
    }
}
