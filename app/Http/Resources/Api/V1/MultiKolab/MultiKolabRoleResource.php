<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\MultiKolabRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MultiKolabRole
 */
class MultiKolabRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'multi_kolab_event_id' => $this->multi_kolab_event_id,
            'status' => $this->status->value,
            'title' => $this->title,
            'eligible_account_type' => $this->eligible_account_type->value,
            'positions_needed' => $this->positions_needed,
            'positions_filled' => $this->positions_filled,
            'required' => $this->required,
            'need' => $this->need,
            'receive' => $this->receive,
            'compensation_type' => $this->compensation_type?->value,
            'requirements' => $this->requirements,
            'details' => $this->details,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
