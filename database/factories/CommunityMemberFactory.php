<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMember>
 */
class CommunityMemberFactory extends Factory
{
    protected $model = CommunityMember::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'tier_id' => null,
            'can_manage' => false,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => now(),
            'tier_assigned_at' => null,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes) => [
            'community_id' => $community->id,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_manage' => true,
        ]);
    }
}
