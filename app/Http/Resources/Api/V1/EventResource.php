<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use App\Services\EventSignupService;
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
            // Legacy single-day field kept for the past-showcase view.
            'date' => $this->event_date->toDateString(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_upcoming' => $this->isUpcoming(),
            'attendee_count' => $this->attendee_count,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'address' => $this->address,
            'location' => $this->location,
            'community_id' => $this->community_id,
            'collaboration_id' => $this->collaboration_id,
            'capacity' => $this->capacity,
            'tier_gate' => $this->tier_gate,
            'photos' => EventPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Per-viewer sign-up state + counts (drives the "I'm going" button).
        $signups = app(EventSignupService::class);
        $data['going_count'] = $signups->goingCount($this->resource);
        $data['waitlist_count'] = $signups->waitlistCount($this->resource);

        $viewer = $request->user();
        $mine = $viewer !== null ? $signups->signupFor($this->resource, $viewer) : null;
        $data['my_signup'] = $mine === null ? null : [
            'status' => $mine->status->value,
            'waitlist_position' => $mine->waitlist_position,
        ];

        // Viewer-scoped gate: false when this member's tier is not permitted, so
        // the app can lock the event (no open into details). Owners always pass.
        $data['can_access'] = $signups->canAccess($this->resource, $viewer);

        if (isset($this->resource->distance_km)) {
            $data['distance_km'] = round((float) $this->resource->distance_km, 2);
        }

        return $data;
    }
}
