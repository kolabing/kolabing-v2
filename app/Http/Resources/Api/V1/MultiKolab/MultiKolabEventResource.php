<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\MultiKolabEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail shape — matches the frozen API contract §3/§6. Expects `roles` to
 * be eager-loaded (see controller) so `role_counts` is computed in-memory,
 * never via extra queries. `viewer_application` is populated by the
 * controller assigning a dynamic `viewer_application` property on the model
 * before wrapping it in this resource (a plain `MultiKolabRoleApplication`
 * model, or null) — never another applicant's data (contract §12: detail
 * never exposes other applicants' pitches).
 *
 * @mixin MultiKolabEvent
 *
 * @property-read \App\Models\MultiKolabRoleApplication|null $viewer_application
 */
class MultiKolabEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'creator_profile_id' => $this->creator_profile_id,
            'creator_profile_type' => $this->relationLoaded('creatorProfile')
                ? $this->creatorProfile?->user_type->value
                : null,
            'title' => $this->title,
            'description' => $this->description,
            'value_summary' => $this->value_summary,
            'venue_needed' => $this->venue_needed,
            'date_mode' => $this->date_mode,
            'event_date' => $this->event_date?->toDateString(),
            'date_range_start' => $this->date_range_start?->toDateString(),
            'date_range_end' => $this->date_range_end?->toDateString(),
            'city' => $this->city,
            'category' => $this->category,
            'rsvp_url' => $this->rsvp_url,
            'eligible_account_type' => $this->eligible_account_type->value,
            'roles' => $this->relationLoaded('roles')
                ? MultiKolabRoleResource::collection($this->roles)
                : [],
            'role_counts' => $this->relationLoaded('roles') ? [
                'total' => $this->roles->count(),
                'open' => $this->roles->where('status', \App\Enums\MultiKolabRoleStatus::Open)->count(),
                'filled' => $this->roles->where('status', \App\Enums\MultiKolabRoleStatus::Filled)->count(),
            ] : null,
            // Set by the controller as a dynamic attribute before wrapping
            // (never another applicant's — contract §12). Eloquent's
            // getAttribute() safely returns null when unset, so this is
            // always present in the response, matching the contract's
            // detail shape (object or null, never omitted).
            // Deprecated but kept for backward compatibility (Review item
            // #11) — was previously an arbitrary `first()` across the
            // viewer's applications on this event; now deterministic via
            // priority accepted > shortlisted > pending > declined >
            // withdrawn, then newest. `viewer_applications` below is the
            // canonical, preferred representation going forward.
            'viewer_application' => $this->resource->viewer_application !== null
                ? new MultiKolabRoleApplicationResource($this->resource->viewer_application)
                : null,
            'viewer_applications' => MultiKolabRoleApplicationResource::collection(
                $this->resource->viewer_applications ?? collect()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
