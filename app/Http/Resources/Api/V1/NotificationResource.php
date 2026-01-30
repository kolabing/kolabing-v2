<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
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
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'actor_name' => $this->actorProfile?->getExtendedProfile()?->name,
            'actor_avatar_url' => $this->actorProfile?->avatar_url,
            'target_id' => $this->target_id,
            'target_type' => $this->target_type,
        ];
    }
}
