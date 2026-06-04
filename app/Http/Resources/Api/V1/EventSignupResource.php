<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EventSignup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSignup
 */
class EventSignupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'status' => $this->status->value,
            'waitlist_position' => $this->waitlist_position,
            'profile' => $this->whenLoaded('profile', fn () => new ProfileSummaryResource($this->profile)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
