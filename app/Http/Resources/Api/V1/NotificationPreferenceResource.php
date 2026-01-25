<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationPreference
 */
class NotificationPreferenceResource extends JsonResource
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
            'email_notifications' => $this->email_notifications,
            'whatsapp_notifications' => $this->whatsapp_notifications,
            'new_application_alerts' => $this->new_application_alerts,
            'collaboration_updates' => $this->collaboration_updates,
            'marketing_tips' => $this->marketing_tips,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
