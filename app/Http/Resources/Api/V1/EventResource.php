<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'partner_name' => $this->partner_name,
            'partner_type' => $this->partner_type,
            'date' => $this->event_date->toDateString(),
            'attendee_count' => $this->attendee_count,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'address' => $this->address,
            'photos' => EventPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (isset($this->resource->distance_km)) {
            $data['distance_km'] = round((float) $this->resource->distance_km, 2);
        }

        return $data;
    }
}
