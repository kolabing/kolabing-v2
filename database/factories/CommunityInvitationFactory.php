<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityInvitationStatus;
use App\Models\Community;
use App\Models\CommunityInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunityInvitation>
 */
class CommunityInvitationFactory extends Factory
{
    protected $model = CommunityInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'email' => fake()->unique()->safeEmail(),
            'tier_id' => null,
            'token' => Str::random(64),
            'invited_by_profile_id' => null,
            'status' => CommunityInvitationStatus::Pending->value,
            'expires_at' => now()->addDays(30),
            'accepted_at' => null,
            'accepted_profile_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => CommunityInvitationStatus::Revoked->value]);
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (): array => ['community_id' => $community->id]);
    }
}
