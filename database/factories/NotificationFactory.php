<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Notification>
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'type' => fake()->randomElement(NotificationType::cases()),
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(10),
            'actor_profile_id' => Profile::factory(),
            'target_id' => null,
            'target_type' => null,
            'read_at' => null,
        ];
    }

    /**
     * Set the notification type to new message.
     */
    public function newMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationType::NewMessage,
            'title' => 'New Message',
        ]);
    }

    /**
     * Set the notification type to application received.
     */
    public function applicationReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationType::ApplicationReceived,
            'title' => 'New Application',
        ]);
    }

    /**
     * Set the notification type to application accepted.
     */
    public function applicationAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationType::ApplicationAccepted,
            'title' => 'Application Accepted',
        ]);
    }

    /**
     * Set the notification type to application declined.
     */
    public function applicationDeclined(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NotificationType::ApplicationDeclined,
            'title' => 'Application Declined',
        ]);
    }

    /**
     * Mark the notification as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the notification as unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Set the recipient profile.
     */
    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * Set the actor profile.
     */
    public function fromActor(Profile $actor): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_profile_id' => $actor->id,
        ]);
    }

    /**
     * Set the target.
     */
    public function forTarget(string $id, string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'target_id' => $id,
            'target_type' => $type,
        ]);
    }
}
