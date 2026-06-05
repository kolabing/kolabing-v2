<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ChatParticipantState;
use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\ChatThreadParticipant;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatThread
 */
class ChatThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof ChatThreadType ? $this->type->value : $this->type;
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'type' => $type,
            'name' => $this->name,
            // Stable per-community slug. The gating key: a tier grants access to a
            // custom chat by listing its slug in permissions.chat_channels, so the
            // app needs it to build the per-tier channel picker.
            'slug' => $this->slug,
            'application_id' => $this->application_id,
            'community_id' => $this->community_id,
            'collaboration_id' => $this->whenLoaded('application', fn () => $this->application?->collaboration?->id),
            'event_id' => $this->event_id,
            'event' => $this->eventSummary(),
            'is_open' => (bool) $this->is_open,
            'can_manage' => $this->viewerCanManage($viewer),
            'is_member' => $this->viewerIsMember($viewer),
            'is_participant' => $this->viewerIsParticipant($viewer),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'participant_summary' => $this->participantSummary(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Event summary for event threads: {id, name, date}.
     *
     * @return array{id: string, name: string|null, date: string|null}|null
     */
    private function eventSummary(): ?array
    {
        if ($this->type !== ChatThreadType::Event || $this->event_id === null) {
            return null;
        }

        $event = $this->relationLoaded('event')
            ? $this->event
            : Event::query()->find($this->event_id);

        if (! $event instanceof Event) {
            return null;
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->event_date?->toDateString(),
        ];
    }

    private function viewerCanManage(?Profile $viewer): bool
    {
        if (! $viewer instanceof Profile || $this->community_id === null) {
            return false;
        }

        return app(ChatService::class)->canManageCommunity($viewer, $this->community_id);
    }

    private function viewerIsMember(?Profile $viewer): bool
    {
        if (! $viewer instanceof Profile || $this->community_id === null) {
            return false;
        }

        return \App\Models\CommunityMember::query()
            ->where('community_id', $this->community_id)
            ->where('profile_id', $viewer->id)
            ->where('status', \App\Enums\CommunityMemberStatus::Active->value)
            ->exists()
            || \App\Models\Community::query()
                ->whereKey($this->community_id)
                ->where('owner_profile_id', $viewer->id)
                ->exists();
    }

    private function viewerIsParticipant(?Profile $viewer): bool
    {
        if (! $viewer instanceof Profile) {
            return false;
        }

        return ChatThreadParticipant::query()
            ->where('thread_id', $this->id)
            ->where('profile_id', $viewer->id)
            ->where('state', ChatParticipantState::Joined->value)
            ->exists();
    }

    /**
     * @return array<int, array{name: string|null, avatar_url: string|null}>
     */
    private function participantSummary(): array
    {
        if (! $this->relationLoaded('application') || $this->application === null) {
            return [];
        }

        $application = $this->application;
        $participants = [
            $application->applicantProfile,
            $application->collabOpportunity?->creatorProfile,
        ];

        $summary = [];
        foreach ($participants as $profile) {
            if ($profile instanceof Profile) {
                $summary[] = [
                    'name' => $this->profileName($profile),
                    'avatar_url' => $profile->avatar_url,
                ];
            }
        }

        return $summary;
    }

    private function profileName(Profile $profile): ?string
    {
        return $profile->businessProfile?->name
            ?? $profile->communityProfile?->name
            ?? null;
    }
}
