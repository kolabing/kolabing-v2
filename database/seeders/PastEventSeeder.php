<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class PastEventSeeder extends Seeder
{
    /**
     * Seed past events with photos for business and community profiles.
     */
    public function run(): void
    {
        $profiles = Profile::query()
            ->whereIn('user_type', ['business', 'community'])
            ->get();

        if ($profiles->isEmpty()) {
            $this->command->warn('No business or community profiles found. Run ProfileSeeder first.');

            return;
        }

        foreach ($profiles as $profile) {
            $eventCount = fake()->numberBetween(2, 5);

            for ($i = 0; $i < $eventCount; $i++) {
                $event = Event::factory()->forProfile($profile)->create([
                    'event_date' => fake()->dateTimeBetween('-6 months', '-1 week'),
                    'is_active' => false,
                ]);

                $photoCount = fake()->numberBetween(2, 6);
                EventPhoto::factory()->count($photoCount)->for($event)->create();
            }
        }

        $totalEvents = Event::query()->count();
        $totalPhotos = EventPhoto::query()->count();
        $this->command->info("Created {$totalEvents} past events with {$totalPhotos} photos.");
    }
}
