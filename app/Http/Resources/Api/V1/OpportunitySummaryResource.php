<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CollabOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight opportunity resource for nested data in other resources.
 * Used within ApplicationResource and CollaborationResource to prevent deep nesting.
 *
 * @mixin CollabOpportunity
 */
class OpportunitySummaryResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'categories' => $this->categories,
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'venue_mode' => $this->venue_mode,
            'preferred_city' => $this->preferred_city,
            'offer_photo' => $this->offer_photo,
            'business_offer' => $this->business_offer,
            'community_deliverables' => $this->community_deliverables,
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
        ];
    }
}
