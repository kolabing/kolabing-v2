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
            // Added, never replacing anything: what the applicant actually
            // answered, which is the substance a leader decides on. Only
            // present when eager-loaded, so a caller that does not need it pays
            // nothing. The question's prompt rides along because a retired
            // question is still readable and the leader needs to see what was
            // asked.
            'answers' => $this->whenLoaded('answers', fn () => $this->answers
                ->map(fn ($answer) => [
                    'question_id' => $answer->question_id,
                    // The wording the applicant saw, falling back to the
                    // question's current prompt for rows written before the
                    // snapshot existed.
                    'prompt' => $answer->prompt_snapshot ?? $answer->question?->prompt,
                    'answer' => $answer->answer,
                ])
                ->values()
                ->all()),
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
