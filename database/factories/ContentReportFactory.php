<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentReport;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
{
    /**
     * @var class-string<ContentReport>
     */
    protected $model = ContentReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_profile_id' => Profile::factory(),
            'target_type' => 'profile',
            'target_id' => (string) fake()->uuid(),
            'reported_profile_id' => null,
            'reason' => fake()->randomElement(['spam', 'harassment', 'inappropriate', 'other']),
            'note' => null,
            'status' => 'open',
        ];
    }
}
