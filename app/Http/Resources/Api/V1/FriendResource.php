<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Friendship;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One friendship row rendered from the authenticated viewer's perspective:
 * the friendship metadata plus a lightweight summary of the OTHER party.
 *
 * @mixin Friendship
 */
class FriendResource extends JsonResource
{
    public function __construct(
        Friendship $friendship,
        private readonly string $viewerProfileId
    ) {
        parent::__construct($friendship);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $otherId = $this->otherProfileId($this->viewerProfileId);
        $other = $this->requester_profile_id === $otherId
            ? $this->requester
            : $this->addressee;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'direction' => $this->requester_profile_id === $this->viewerProfileId ? 'outgoing' : 'incoming',
            'requester_profile_id' => $this->requester_profile_id,
            'addressee_profile_id' => $this->addressee_profile_id,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => $this->profileSummary($other),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profileSummary(?Profile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $extended = $profile->getExtendedProfile();

        return [
            'id' => $profile->id,
            'name' => $extended && ! empty($extended->name)
                ? $extended->name
                : Str::before($profile->email, '@'),
            'avatar_url' => $profile->avatar_url ?? ($extended->profile_photo ?? null),
            'user_type' => $profile->user_type->value,
        ];
    }
}
