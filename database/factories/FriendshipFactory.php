<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    protected $model = Friendship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_profile_id' => Profile::factory()->attendee(),
            'addressee_profile_id' => Profile::factory()->attendee(),
            'status' => FriendshipStatus::Pending->value,
            'responded_at' => null,
        ];
    }

    public function between(Profile $requester, Profile $addressee): static
    {
        return $this->state(fn (): array => [
            'requester_profile_id' => $requester->id,
            'addressee_profile_id' => $addressee->id,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => FriendshipStatus::Accepted->value,
            'responded_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => [
            'status' => FriendshipStatus::Blocked->value,
            'responded_at' => now(),
        ]);
    }
}
