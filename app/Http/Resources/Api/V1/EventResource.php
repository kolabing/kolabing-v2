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
            // Host community name + 17-slug type (eager-loaded to avoid N+1).
            // community_type is the host community's `type` (the unified vocabulary),
            // NOT the 5-value App\Enums\CommunityType placeholder.
            'community_name' => $this->whenLoaded('community', fn () => $this->community?->name),
            'community_type' => $this->whenLoaded('community', fn () => $this->community?->type),
            // Effective city name: the event's own city when set, else the host
            // community's profile city. Mirrors the discover effective-city rule.
            'city_id' => $this->city_id,
            'city_name' => $this->effectiveCityName(),
            'collaboration_id' => $this->collaboration_id,
            'capacity' => $this->capacity,
            'tier_gate' => $this->tier_gate,
            'visibility' => ($this->visibility ?? \App\Enums\EventVisibility::Members)->value,
            'series_id' => $this->series_id,
            'occurrence_index' => $this->occurrence_index,
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

    /**
     * The event's effective city name: its own city when set, otherwise the host
     * community's profile city. Only reads already-loaded relations so it never
     * triggers an N+1 (discover eager-loads `city` + `community.communityProfile`).
     */
    private function effectiveCityName(): ?string
    {
        if ($this->city_id !== null && $this->relationLoaded('city')) {
            $name = $this->city?->name;
            if ($name !== null) {
                return $name;
            }
        }

        if ($this->relationLoaded('community')) {
            $community = $this->community;
            if ($community !== null && $community->relationLoaded('communityProfile')) {
                $profile = $community->communityProfile;
                if ($profile !== null && $profile->relationLoaded('city')) {
                    return $profile->city?->name;
                }
            }
        }

        return null;
    }
}
