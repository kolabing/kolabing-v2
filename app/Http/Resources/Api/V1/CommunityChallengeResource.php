<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A challenge a community plays, and how strictly (kolabing-app#150).
 *
 * @mixin CommunityChallenge
 */
class CommunityChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'challenge_id' => $this->challenge_id,
            'challenge' => new ChallengeResource($this->whenLoaded('challenge')),
            'allow_repeat_with_same_person' => $this->allow_repeat_with_same_person,
            'requires_new_person' => $this->requires_new_person,
        ];
    }
}
