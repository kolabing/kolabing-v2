<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attendee-facing PUBLIC EVENTS feed card (Batch 3). Trimmed event payload plus a
 * community summary + going count so an attendee can discover and RSVP without a
 * membership. `going_count` is supplied by the discovery query's withCount.
 *
 * @mixin Event
 */
class PublicEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'partner_name' => $this->partner_name,
            'partner_type' => $this->partner_type,
            'date' => $this->event_date->toDateString(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_upcoming' => $this->isUpcoming(),
            'visibility' => $this->visibility->value,
            'location' => $this->location,
            'address' => $this->address,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'capacity' => $this->capacity,
            'going_count' => (int) ($this->going_count ?? 0),
            'photos' => EventPhotoResource::collection($this->whenLoaded('photos')),
            'community' => $this->whenLoaded('community', fn (): ?array => $this->community === null ? null : [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
                'type' => $this->community->type->value,
                'avatar_url' => $this->community->avatar_url,
            ]),
        ];
    }
}
