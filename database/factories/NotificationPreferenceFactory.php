<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NotificationPreference>
     */
    protected $model = NotificationPreference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'email_notifications' => true,
            'whatsapp_notifications' => true,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => false,
        ];
    }

    /**
     * Indicate that all notifications are enabled.
     */
    public function allEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_notifications' => true,
            'whatsapp_notifications' => true,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => true,
        ]);
    }

    /**
     * Indicate that all notifications are disabled.
     */
    public function allDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_notifications' => false,
            'whatsapp_notifications' => false,
            'new_application_alerts' => false,
            'collaboration_updates' => false,
            'marketing_tips' => false,
        ]);
    }
}
