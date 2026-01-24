<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessSubscription
 */
class SubscriptionResource extends JsonResource
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
            'status' => $this->status->value,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
        ];
    }
}
