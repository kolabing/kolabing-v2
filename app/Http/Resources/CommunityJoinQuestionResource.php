<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CommunityJoinQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityJoinQuestion
 */
class CommunityJoinQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'position' => $this->position,
            'prompt' => $this->prompt,
            'required' => $this->required,
            // Exposed so a leader's management screen can show a retired
            // question; the applicant-facing list only ever contains active
            // ones, so this is always true there.
            'is_active' => $this->is_active,
        ];
    }
}
