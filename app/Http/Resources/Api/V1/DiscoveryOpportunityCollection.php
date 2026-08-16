<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\MultiKolab\MultiKolabRoleExploreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Heterogeneous Explore feed collection: each entry is a wrapped
 * `{item_type, model, ...}` array produced by
 * {@see \App\Services\DiscoveryOpportunityService::discover()}, routed to
 * the resource matching its `item_type` — either an ordinary Kolab
 * ({@see DiscoveryOpportunityResource}) or an open Multi-Kolab role
 * ({@see MultiKolabRoleExploreResource}).
 *
 * Deliberately extends {@see JsonResource}, not `ResourceCollection`:
 * `ResourceCollection` auto-guesses a single `collects` class from this
 * class's own name (stripping the `Collection` suffix) and eagerly wraps
 * every item through it *before* `toArray()` runs, which would force every
 * item — including Multi-Kolab roles — through `DiscoveryOpportunityResource`
 * and crash. `JsonResource` performs no such wrapping, leaving the raw
 * wrapped-array collection available in `$this->resource` for `toArray()`
 * to route itself.
 */
class DiscoveryOpportunityCollection extends JsonResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return collect($this->resource)
            ->map(function (array $item) use ($request): array {
                $resource = $item['item_type'] === 'multi_kolab_role'
                    ? new MultiKolabRoleExploreResource($item['model'])
                    : new DiscoveryOpportunityResource($item['model']);

                return $resource->toArray($request);
            })
            ->values()
            ->all();
    }
}
