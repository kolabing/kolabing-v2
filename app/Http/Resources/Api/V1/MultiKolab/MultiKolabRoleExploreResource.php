<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\MultiKolabRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One open Multi-Kolab role, shaped as a single Explore feed item
 * (`item_type: "multi_kolab_role"`) so it can sit in the same paginated
 * list as an ordinary {@see \App\Http\Resources\Api\V1\DiscoveryOpportunityResource}
 * Kolab item — see `docs/superpowers/specs/2026-08-12-multi-kolab-event-api-contract.md`
 * §13 for the design decision.
 *
 * Expects the query to have eager-loaded `event.creatorProfile.businessProfile`
 * / `event.creatorProfile.communityProfile` (see
 * {@see \App\Services\DiscoveryOpportunityService::buildMultiKolabRoleItems()})
 * so this resource never triggers a query per row, and a
 * `discovery_match_score` attribute set on the model by the same method.
 *
 * @mixin MultiKolabRole
 */
class MultiKolabRoleExploreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->event;
        $creatorProfile = $event->creatorProfile;
        $displayName = $creatorProfile?->isBusiness()
            ? $creatorProfile->businessProfile?->name
            : $creatorProfile?->communityProfile?->name;

        return [
            'item_type' => 'multi_kolab_role',
            'id' => $this->id,
            'multi_kolab_event_id' => $event->id,
            'role_title' => $this->title,
            'looking_for' => [
                'eligible_account_type' => $this->eligible_account_type->value,
                'required' => $this->required,
            ],
            'event_title' => $event->title,
            'city' => $event->city,
            'target_date' => [
                'mode' => $event->date_mode,
                'date' => $event->event_date?->toDateString(),
                'range_start' => $event->date_range_start?->toDateString(),
                'range_end' => $event->date_range_end?->toDateString(),
            ],
            'compensation' => [
                'type' => $this->compensation_type?->value,
                'need' => $this->need,
                'receive' => $this->receive,
                'value_summary' => $event->value_summary,
            ],
            'positions_needed' => $this->positions_needed,
            'positions_filled' => $this->positions_filled,
            'positions_remaining' => max($this->positions_needed - $this->positions_filled, 0),
            'match_score' => (int) ($this->getAttribute('discovery_match_score') ?? 0),
            'image_url' => $creatorProfile?->avatar_url,
            'creator_profile' => [
                'id' => $creatorProfile?->id,
                'display_name' => $displayName,
                'avatar_url' => $creatorProfile?->avatar_url,
            ],
            'rsvp' => $event->rsvp_url !== null ? ['url' => $event->rsvp_url] : null,
            'published_at' => $event->published_at?->toIso8601String(),
        ];
    }
}
