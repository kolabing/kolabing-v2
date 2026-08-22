<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Services\EventSignupService;
use App\Support\CheckinLink;
use App\Support\QrCode;
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
            // The host's `profiles.id` (NOT community_id, which is a communities.id):
            // lets the app open the host's public profile without 404ing on the
            // /profiles/{id} route (which binds profiles.id). Prefers the eager-loaded
            // community owner's profile; falls back to the event's own host profile_id,
            // which is always a valid profiles.id. See kolabing-app F1.
            'host_profile_id' => $this->relationLoaded('community') && $this->community
                ? $this->community->owner_profile_id
                : $this->profile_id,
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
        $data['going_count'] = $this->preloadedAttribute('going_count')
            ?? $signups->goingCount($this->resource);
        $data['waitlist_count'] = $this->preloadedAttribute('waitlist_count')
            ?? $signups->waitlistCount($this->resource);

        $viewer = $request->user();

        /*
         * Door state, for the host only. The token and the typable code are the
         * permission to be counted as present, so they must never reach an
         * attendee's payload — anyone holding them could check themselves in.
         * The QR ships as an inline SVG because the panel authenticates with a
         * bearer token, which an <img src> cannot carry.
         */
        if ($this->resource->isHostedBy($viewer)) {
            $open = $this->checkin_token !== null
                && $this->is_active
                && ($this->checkin_token_expires_at === null || $this->checkin_token_expires_at->isFuture());

            $data['checkin'] = [
                'is_open' => $open,
                'code' => $open ? $this->checkin_code : null,
                'url' => $open ? CheckinLink::urlFor($this->resource) : null,
                'qr_svg' => $open ? QrCode::svg(CheckinLink::urlFor($this->resource)) : null,
                'expires_at' => $this->checkin_token_expires_at?->toIso8601String(),
                'checked_in_count' => EventCheckin::query()->where('event_id', $this->id)->count(),
            ];
        }

        if ($this->hasPreloadedAttribute('viewer_signup_status')) {
            $preloadedSignupStatus = $this->preloadedAttribute('viewer_signup_status');
            $data['my_signup'] = $preloadedSignupStatus === null
                ? null
                : [
                    'status' => $preloadedSignupStatus,
                    'waitlist_position' => $this->preloadedAttribute('viewer_signup_waitlist_position'),
                ];
        } else {
            $mine = $viewer !== null ? $signups->signupFor($this->resource, $viewer) : null;
            $data['my_signup'] = $mine === null ? null : [
                'status' => $mine->status->value,
                'waitlist_position' => $mine->waitlist_position,
            ];
        }

        // Viewer-scoped gate: false when this member's tier is not permitted, so
        // the app can lock the event (no open into details). Owners always pass.
        $data['can_access'] = $this->preloadedAttribute('viewer_can_access')
            ?? $signups->canAccess($this->resource, $viewer);

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

    private function preloadedAttribute(string $key): mixed
    {
        return array_key_exists($key, $this->resource->getAttributes())
            ? $this->resource->getAttribute($key)
            : null;
    }

    private function hasPreloadedAttribute(string $key): bool
    {
        return array_key_exists($key, $this->resource->getAttributes());
    }
}
