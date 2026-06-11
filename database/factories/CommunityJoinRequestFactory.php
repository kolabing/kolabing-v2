<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JoinRequestStatus;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityJoinRequest>
 */
class CommunityJoinRequestFactory extends Factory
{
    protected $model = CommunityJoinRequest::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory()->inviteOnly(),
            'profile_id' => Profile::factory()->attendee(),
            'status' => JoinRequestStatus::Pending->value,
            'decided_by' => null,
            'requested_at' => now(),
            'decided_at' => null,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes) => [
            'community_id' => $community->id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JoinRequestStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JoinRequestStatus::Declined->value,
            'decided_at' => now(),
        ]);
    }
}
