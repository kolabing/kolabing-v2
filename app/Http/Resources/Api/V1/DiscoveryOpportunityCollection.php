<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DiscoveryOpportunityCollection extends ResourceCollection
{
    /**
     * @var string
     */
    public $collects = DiscoveryOpportunityResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->values()->all();
    }
}
