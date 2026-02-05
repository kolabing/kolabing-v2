<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPhoto>
 */
class EventPhotoFactory extends Factory
{
    protected $model = EventPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'url' => $this->faker->imageUrl(800, 600),
            'thumbnail_url' => null,
            'sort_order' => $this->faker->numberBetween(0, 4),
        ];
    }

    /**
     * Set the event for this photo.
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => [
            'event_id' => $event->id,
        ]);
    }
}
