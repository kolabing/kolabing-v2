<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the attendee public-profile aggregate built by
 * AttendeeProfileService::buildPublicProfile(). The underlying resource is the
 * pre-shaped associative array, so this resource is a thin pass-through that
 * keeps the API surface consistent with the other V1 resources.
 *
 * @property-read array<string, mixed> $resource
 */
class AttendeePublicProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $aggregate */
        $aggregate = $this->resource;

        return $aggregate;
    }
}
