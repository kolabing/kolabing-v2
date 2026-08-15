<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\MultiKolabEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explore listing shape — matches the frozen API contract §6 (summary).
 *
 * Expects the query to have eager-loaded `creatorProfile.businessProfile`,
 * `creatorProfile.communityProfile`, and the `roles_count` /
 * `open_roles_count` / `filled_roles_count` aggregates (see
 * {@see \App\Http\Controllers\Api\V1\MultiKolabEventController::index()}) so
 * this resource never triggers a query per row.
 *
 * @mixin MultiKolabEvent
 */
class MultiKolabEventSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'title' => $this->title,
            'value_summary' => $this->value_summary,
            'city' => $this->city,
            'category' => $this->category,
            'event_date' => $this->event_date?->toDateString(),
            'date_mode' => $this->date_mode,
            'role_counts' => [
                'total' => $this->roles_count ?? 0,
                'open' => $this->open_roles_count ?? 0,
                'filled' => $this->filled_roles_count ?? 0,
            ],
            'eligible_account_type' => $this->eligible_account_type->value,
            'creator_profile' => $this->whenLoaded(
                'creatorProfile',
                fn () => new MultiKolabCreatorSummaryResource($this->creatorProfile),
            ),
        ];
    }
}
