<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ChallengeCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChallengeCompletion
 */
class ChallengeCompletionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'challenge' => new ChallengeResource($this->whenLoaded('challenge')),
            'event_id' => $this->event_id,
            'challenger_profile_id' => $this->challenger_profile_id,
            'verifier_profile_id' => $this->verifier_profile_id,
            'status' => $this->status->value,
            'points_earned' => $this->points_earned,
            // Already absolute — FileUploadService returns a URL on upload (#216).
            'proof_photo_url' => $this->proof_photo_url,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
