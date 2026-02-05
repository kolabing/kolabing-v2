<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class EventPartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->resource->getExtendedProfile();

        return [
            'id' => $this->id,
            'name' => $extendedProfile?->name,
            'profile_photo' => $extendedProfile?->profile_photo,
            'type' => $this->user_type->value,
        ];
    }
}
