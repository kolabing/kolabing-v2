<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\ActiveProfileScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies ActiveProfileScope to a sub-profile model and hands the admin panel
 * the one escape hatch it needs (#254).
 *
 * @see ActiveProfileScope for why Profile itself is not scoped.
 */
trait HasActiveProfileScope
{
    public static function bootHasActiveProfileScope(): void
    {
        static::addGlobalScope(new ActiveProfileScope);
    }

    /**
     * Include sub-profiles whose owning profile is switched off.
     *
     * Admin surfaces only. An app-facing endpoint that calls this is a bug:
     * a deactivated account must not be reachable from the app at all.
     *
     * @return Builder<static>
     */
    public static function withInactiveProfiles(): Builder
    {
        return static::query()->withoutGlobalScope(ActiveProfileScope::class);
    }
}
