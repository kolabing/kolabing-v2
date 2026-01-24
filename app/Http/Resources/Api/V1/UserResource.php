<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\UserType;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'user_type' => $this->user_type->value,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'onboarding_completed' => $this->onboarding_completed,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Add extended profile based on user type
        if ($this->user_type === UserType::Business) {
            $data['business_profile'] = $this->whenLoaded('businessProfile', function () {
                return new BusinessProfileResource($this->businessProfile);
            });

            $data['subscription'] = $this->whenLoaded('subscription', function () {
                return $this->subscription ? new SubscriptionResource($this->subscription) : null;
            });
        } else {
            $data['community_profile'] = $this->whenLoaded('communityProfile', function () {
                return new CommunityProfileResource($this->communityProfile);
            });
        }

        return $data;
    }
}
