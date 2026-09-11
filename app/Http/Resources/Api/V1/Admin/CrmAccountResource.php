<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\CrmAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit field allowlist for the token-authenticated CRM read API (red-teamed 2026-09-11:
 * CrmAccount has no $hidden array, so serializing the model directly leaked free-text sales
 * `notes` and the internal `linked_profile_id` FK — neither of which the Blade admin panel
 * exposes even to a logged-in maintainer by default). Contact fields (email/phone/instagram/
 * whatsapp) stay: they're the actual point of this endpoint (building a CRM export).
 *
 * @mixin CrmAccount
 */
class CrmAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'owner' => $this->owner,
            'email' => $this->email,
            'phone' => $this->phone,
            'instagram_handle' => $this->instagram_handle,
            'whatsapp' => $this->whatsapp,
            'next_action' => $this->next_action,
            'score' => $this->score,
            'metrics' => $this->metrics,
            'last_activity_at' => $this->last_activity_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
