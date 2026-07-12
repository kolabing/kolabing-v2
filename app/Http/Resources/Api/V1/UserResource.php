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
            'onboarding_completed' => $this->onboardingCompleted(),
            'handle' => $this->handle,
            'interests' => $this->interests ?? [],
            'analytics_opt_out' => (bool) $this->analytics_opt_out,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'terms' => [
                'current_version' => (string) config('legal.terms_version'),
                'accepted_version' => $this->terms_version,
                'accepted_at' => $this->terms_accepted_at?->toIso8601String(),
                'needs_acceptance' => $this->needsTermsAcceptance(),
            ],
        ];

        // Add extended profile based on user type
        if ($this->user_type === UserType::Business) {
            $data['business_profile'] = $this->whenLoaded('businessProfile', function () {
                return new BusinessProfileResource($this->businessProfile);
            });

            $data['subscription'] = $this->whenLoaded('subscription', function () {
                return $this->subscription ? new SubscriptionResource($this->subscription) : null;
            });

            $data['has_active_subscription'] = $this->hasActiveSubscription();
        } elseif ($this->user_type === UserType::Attendee) {
            // Attendee identity (name, avatar, city) lives on the base profile.
            $data['name'] = $this->name;
            $data['city_id'] = $this->city_id;
            $data['city_name'] = $this->whenLoaded('city', fn () => $this->city?->name);

            $data['attendee_profile'] = $this->whenLoaded('attendeeProfile', function () {
                $attendee = $this->attendeeProfile;

                return $attendee ? [
                    'id' => $attendee->id,
                    'total_points' => $attendee->total_points,
                    'total_challenges_completed' => $attendee->total_challenges_completed,
                    'total_events_attended' => $attendee->total_events_attended,
                    'global_rank' => $attendee->global_rank,
                ] : null;
            });
        } else {
            $data['community_profile'] = $this->whenLoaded('communityProfile', function () {
                return new CommunityProfileResource($this->communityProfile);
            });
        }

        return $data;
    }
}
