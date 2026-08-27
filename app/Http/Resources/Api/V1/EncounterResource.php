<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meeting, from the viewer's side (#244, #246).
 *
 * @mixin Encounter
 */
class EncounterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $other = $this->whenLoaded('other');

        return [
            'id' => $this->id,
            'other_profile_id' => $this->other_profile_id,
            'other_name' => $this->other_profile_id !== null && $other !== null
                ? $other->name
                : null,
            'other_avatar_url' => $this->other_profile_id !== null && $other !== null
                ? $other->avatar_url
                : null,
            'ghost_name' => $this->ghost_name,
            'community_id' => $this->community_id,
            'community_name' => $this->whenLoaded('community', fn () => $this->community?->name),
            'first_met_event_id' => $this->event_id,
            'first_met_event_name' => $this->whenLoaded('event', fn () => $this->event?->name),
            'first_met_at' => $this->met_at?->toIso8601String(),
            'last_met_at' => $this->met_at?->toIso8601String(),
            'times_met' => $this->times_met,
            'photo_url' => $this->proof_photo_url,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            // What is waiting on this person to join. Named on the inviter's
            // screen precisely because it is NOT paid yet (#246).
            'pending_points' => $this->pending_points,
        ];
    }
}
