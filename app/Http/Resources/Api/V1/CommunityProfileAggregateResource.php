<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Community;
use App\Models\CommunityTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The rich aggregate community public-profile payload (Batch 6).
 *
 * Wraps the array produced by CommunityProfileService::build(). The shape here
 * is authoritative for the app's community-profile screen.
 */
class CommunityProfileAggregateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        /** @var Community $community */
        $community = $payload['community'];

        return [
            'community' => [
                'id' => $community->id,
                'owner_profile_id' => $community->owner_profile_id,
                'community_profile_id' => $community->community_profile_id,
                'name' => $community->name,
                'slug' => $community->slug,
                'type' => $community->type->value,
                'description' => $community->description,
                'avatar_url' => $community->avatar_url,
                'cover_url' => $community->cover_url,
                'is_primary' => $community->is_primary,
                'join_policy' => $community->join_policy->value,
                'member_count' => $community->memberCount(),
                'event_count' => $community->eventCount(),
            ],
            'tiers' => $payload['tiers']->map(fn (CommunityTier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'rank' => $tier->rank,
                'color' => $tier->color,
                'assignment_rule' => $tier->assignment_rule->value,
                'threshold' => $tier->threshold,
                'is_default' => $tier->is_default,
            ])->values()->all(),
            'upcoming_events' => $payload['upcoming_events'],
            'members_preview' => $payload['members_preview'],
            'gallery' => $payload['gallery'],
            'chats' => $payload['chats'],
            'viewer' => $payload['viewer'],
        ];
    }
}
