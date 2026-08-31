<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\ActiveOwnerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * An opt-in owner filter for models that must NOT carry a global one (#258).
 *
 * Kolabs and Multi-Kolab events sit in a lifecycle with a counterparty, and a
 * global scope on them is actively harmful — measured, not assumed:
 *
 *     business creates a kolab -> community applies -> business is deactivated
 *     application->kolab            null
 *     whereHas('kolab')             0 rows
 *
 * The still-active community loses its own application, because the row it
 * hangs off vanished underneath it. Hiding a deactivated business must never
 * cost the other party their record.
 *
 * So the filter is explicit and lives at the call site: discovery and Explore
 * opt in, and every counterparty path (applications, collaborations, chats)
 * keeps seeing the row.
 *
 * @see ActiveOwnerScope for the directory models, where a global scope IS right.
 */
trait FiltersByActiveOwner
{
    /**
     * Limit to rows whose owner has not been switched off.
     *
     * @param  Builder<static>  $query
     */
    public function scopeFromActiveOwner(Builder $query): void
    {
        (new ActiveOwnerScope(static::ownerProfileKey()))->apply($query, $this);
    }

    /**
     * The column naming this row's owner.
     */
    public static function ownerProfileKey(): string
    {
        return 'creator_profile_id';
    }
}
