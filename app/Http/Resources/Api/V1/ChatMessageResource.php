<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
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
            'application_id' => $this->application_id,
            'thread_id' => $this->thread_id,
            'sender_profile' => $this->whenLoaded('senderProfile', function () {
                return $this->senderProfile === null ? null : new ProfileSummaryResource($this->senderProfile);
            }),
            // 'profile' | 'team'. A team message has no sender_profile: the app
            // shows "Kolabing team" and renders `offer` as a card when present.
            'sender_type' => ($this->sender_type ?? \App\Enums\ChatSenderType::Profile)->value,
            'sender_name' => $this->isFromTeam() ? 'Kolabing team' : null,
            'offer' => $this->whenLoaded('teamOffer', fn () => $this->teamOffer === null ? null : new KolabingTeamOfferResource($this->teamOffer)),
            'content' => $this->content,
            'is_own' => $this->sender_profile_id !== null && $request->user()?->id === $this->sender_profile_id,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
