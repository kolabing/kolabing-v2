<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The one reorder rule, shared by the profile gallery and an event's photos.
 *
 * Two properties matter and both are load-bearing:
 *  - ids the caller does not own are ignored, never written, so a guessed id
 *    cannot touch someone else's row;
 *  - owned ids the caller omitted keep their relative order *after* the supplied
 *    ones, so a partial list can never make a photo vanish from the grid.
 */
class PhotoOrderingService
{
    /**
     * @param  array<int, mixed>  $requestedIds  the client's desired order
     * @param  array<int, string>  $ownedIds  the caller's ids, in current order
     * @return array<int, string> the full id list in its new order
     */
    public function resolve(array $requestedIds, array $ownedIds): array
    {
        $owned = array_values($ownedIds);
        $ownedLookup = array_flip($owned);

        $ordered = [];

        foreach ($requestedIds as $id) {
            if (! is_string($id) || ! array_key_exists($id, $ownedLookup)) {
                continue;
            }

            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        foreach ($owned as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }
}
