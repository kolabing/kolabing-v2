<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\ActiveOwnerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies ActiveOwnerScope to a model that belongs to a profile, and hands the
 * admin panel the one escape hatch it needs (#254, #258).
 *
 * The using model declares which column holds the owner:
 *
 *     protected static string $activeOwnerKey = 'owner_profile_id';
 *
 * It defaults to `profile_id`, which is what most tables use.
 */
trait HasActiveOwnerScope
{
    public static function bootHasActiveOwnerScope(): void
    {
        static::addGlobalScope(new ActiveOwnerScope(static::activeOwnerKey()));
    }

    public static function activeOwnerKey(): string
    {
        return property_exists(static::class, 'activeOwnerKey')
            ? static::$activeOwnerKey
            : 'profile_id';
    }

    /**
     * Include rows owned by a switched-off profile.
     *
     * Admin surfaces and deliberate counterparty reads only — an app-facing
     * discovery endpoint that calls this is a bug.
     *
     * @return Builder<static>
     */
    public static function withInactiveOwners(): Builder
    {
        return static::query()->withoutGlobalScope(ActiveOwnerScope::class);
    }
}
