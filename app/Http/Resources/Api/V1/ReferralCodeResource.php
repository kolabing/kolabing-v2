<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralCode
 */
class ReferralCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'referral_link' => config('app.url').'/ref/'.$this->code,
            'total_conversions' => $this->total_conversions,
            'total_points_earned' => $this->total_points_earned,
        ];
    }
}
