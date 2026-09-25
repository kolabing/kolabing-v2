<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Notification;
use App\Support\CommunityIdentityMask;
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
        // ROLES §2.5: a free business must not learn a community's identity
        // from a notification. The mask is decided at read time, so a business
        // that subscribes sees the actor on its existing notifications. The
        // id is withheld too: a masked name beside a usable profile id is not
        // a mask (same shape as SuggestionResource).
        $masked = $this->actorProfile !== null
            && CommunityIdentityMask::applies($request->user(), $this->actorProfile);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Additive: lets a client open the actor's profile from a notification.
            'actor_profile_id' => $masked ? null : $this->actor_profile_id,
            'actor_name' => $masked ? null : $this->actorProfile?->getExtendedProfile()?->name,
            'actor_avatar_url' => $masked ? null : $this->actorProfile?->avatar_url,
            'actor_identity_masked' => $masked,
            'target_id' => $this->target_id,
            'target_type' => $this->target_type,
        ];
    }
}
