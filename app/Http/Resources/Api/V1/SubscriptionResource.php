<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\SubscriptionSource;
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
            'status_label' => $this->status->label(),
            'source' => $this->source->value,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'is_active' => $this->isActive(),
            'days_remaining' => $this->current_period_end
                ? (int) max(0, now()->diffInDays($this->current_period_end, false))
                : null,
            'apple_product_id' => $this->source === SubscriptionSource::AppleIap
                ? $this->apple_product_id
                : null,
        ];
    }
}
