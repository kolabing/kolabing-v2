<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Kolab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kolab
 */
class KolabResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intent_type' => $this->intent_type->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'description' => $this->description,
            'preferred_city' => $this->preferred_city,
            'area' => $this->area,
            'media' => $this->media ?? [],
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'selected_time' => $this->selected_time,
            'recurring_days' => $this->recurring_days ?? [],
            'needs' => $this->needs ?? [],
            'community_types' => $this->community_types ?? [],
            'community_size' => $this->community_size,
            'typical_attendance' => $this->typical_attendance,
            'offers_in_return' => $this->offers_in_return ?? [],
            'venue_preference' => $this->venue_preference,
            'venue_name' => $this->venue_name,
            'venue_type' => $this->venue_type,
            'capacity' => $this->capacity,
            'venue_address' => $this->venue_address,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'offering' => $this->offering ?? [],
            'seeking_communities' => $this->seeking_communities ?? [],
            'min_community_size' => $this->min_community_size,
            'expects' => $this->expects ?? [],
            'past_events' => $this->past_events ?? [],
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
        ];
    }
}
