<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatParticipantState;
use App\Models\ChatThread;
use App\Models\ChatThreadParticipant;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatThreadParticipant>
 */
class ChatThreadParticipantFactory extends Factory
{
    protected $model = ChatThreadParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => ChatThread::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'state' => ChatParticipantState::Joined->value,
            'joined_at' => now(),
            'banned_at' => null,
            'banned_by' => null,
        ];
    }

    public function forThread(ChatThread $thread): static
    {
        return $this->state(fn (array $attributes): array => [
            'thread_id' => $thread->id,
        ]);
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (array $attributes): array => [
            'profile_id' => $profile->id,
        ]);
    }

    public function banned(?Profile $bannedBy = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => ChatParticipantState::Banned->value,
            'banned_at' => now(),
            'banned_by' => $bannedBy?->id,
        ]);
    }
}
