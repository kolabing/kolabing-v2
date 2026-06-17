<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight opportunity resource for nested data in other resources.
 * Used within ApplicationResource and CollaborationResource to prevent deep nesting.
 *
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
            'offer_headline' => $this->offer_headline,
            'base_offer' => $this->base_offer,
            'negotiation_triggers' => $this->negotiation_triggers ?? [],
            'status' => $this->status->value,
            'categories' => $this->categories ?? $this->community_types ?? [],
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'selected_time' => $this->selected_time,
            'recurring_days' => $this->recurring_days,
            'venue_mode' => $this->venue_mode ?? $this->venue_preference,
            'preferred_city' => $this->preferred_city,
            'offer_photo' => $this->resolveOfferPhoto(),
            'business_offer' => $this->business_offer ?? $this->offering ?? $this->offers_in_return ?? [],
            'community_deliverables' => $this->community_deliverables ?? $this->expects ?? $this->needs ?? [],
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
        ];
    }

    private function resolveOfferPhoto(): ?string
    {
        if (isset($this->offer_photo) && is_string($this->offer_photo)) {
            return $this->offer_photo;
        }

        if (! is_array($this->media ?? null)) {
            return null;
        }

        $first = $this->media[0] ?? null;
        if (is_string($first)) {
            return $first;
        }

        return is_array($first) && isset($first['url']) && is_string($first['url'])
            ? $first['url']
            : null;
    }
}
