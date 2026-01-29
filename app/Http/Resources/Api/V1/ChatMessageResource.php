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
            'sender_profile' => $this->whenLoaded('senderProfile', function () {
                return new ProfileSummaryResource($this->senderProfile);
            }),
            'content' => $this->content,
            'is_own' => $request->user()?->id === $this->sender_profile_id,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
