<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityJoinRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin CommunityJoinRequest
 */
class CommunityJoinRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'profile_id' => $this->profile_id,
            'status' => $this->status->value,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', fn () => [
                'name' => $this->profileDisplayName(),
                'avatar_url' => $this->profileAvatarUrl(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function profileDisplayName(): ?string
    {
        $extended = $this->profile?->getExtendedProfile();

        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return $this->profile ? Str::before($this->profile->email, '@') : null;
    }

    private function profileAvatarUrl(): ?string
    {
        $extended = $this->profile?->getExtendedProfile();

        return $this->profile?->avatar_url
            ?? ($extended->profile_photo ?? null);
    }
}
