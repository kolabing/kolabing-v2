<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChatMessage>
     */
    protected $model = ChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'sender_profile_id' => Profile::factory(),
            'content' => fake()->paragraph(),
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the message has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    /**
     * Set the application for this message.
     */
    public function forApplication(Application $application): static
    {
        return $this->state(fn (array $attributes) => [
            'application_id' => $application->id,
        ]);
    }

    /**
     * Set the sender for this message.
     */
    public function fromSender(Profile $sender): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_profile_id' => $sender->id,
        ]);
    }
}
