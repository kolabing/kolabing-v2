<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\Profile;
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
            'series_id' => $this->series_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            // Preview of the most recent message so the chat list shows real text
            // instead of a "Tap to open" placeholder (#8). Eager-loaded via
            // latestMessage()->latestOfMany() to avoid N+1; null on empty threads,
            // key omitted when the relation isn't loaded (the app falls back).
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'content' => $this->latestMessage->content,
                'created_at' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'participant_summary' => $this->participantSummary(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
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
        $opportunity = $application->kolab;
        $participants = [
            $application->applicantProfile,
            $opportunity?->creatorProfile,
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
