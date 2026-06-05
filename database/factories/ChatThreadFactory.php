<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'type' => ChatThreadType::CommunityCustom->value,
            'community_id' => Community::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_open' => false,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes): array => [
            'community_id' => $community->id,
        ]);
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatThreadType::CommunityCustom->value,
        ]);
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatThreadType::CommunityMain->value,
            'slug' => null,
        ]);
    }

    public function event(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ChatThreadType::Event->value,
            'slug' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => true,
        ]);
    }

    public function createdBy(Profile $profile): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_by' => $profile->id,
        ]);
    }
}
