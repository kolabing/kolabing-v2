<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\CommunityMember;
use App\Models\EventCheckin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventCheckin
 */
class EventCheckinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'profile_id' => $this->profile_id,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // The host community and whether this person belongs to it
            // (kolabing-app#148). Just turning up is the one moment worth asking
            // "want to become a member?", and the app cannot ask without
            // knowing who to ask about.
            //
            // FACTS, not a decision: no `should_prompt` boolean, because that
            // would bake "ask every time" into the API and leave the client
            // unable to decide otherwise (it also has to remember a dismissal,
            // which only it knows about).
            'community' => $this->communitySummary(),
            'is_member' => $this->viewerIsMember(),
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function communitySummary(): ?array
    {
        $community = $this->event?->community;

        if ($community === null) {
            return null;
        }

        return ['id' => $community->id, 'name' => $community->name];
    }

    /**
     * Whether the person who just checked in already belongs to the host
     * community. Null when there is no community to belong to.
     */
    private function viewerIsMember(): ?bool
    {
        $community = $this->event?->community;

        if ($community === null) {
            return null;
        }

        return CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $this->profile_id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }
}
