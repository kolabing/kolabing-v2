<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SuggestionAudience;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KolabSuggestion>
 */
class KolabSuggestionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<KolabSuggestion>
     */
    protected $model = KolabSuggestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => SuggestionAudience::Business,
            'viewer_profile_id' => Profile::factory()->business(),
            'counterpart_profile_id' => Profile::factory()->community(),
            'city_id' => null,
            'score' => fake()->numberBetween(45, 95),
            'confidence' => 'medium',
            'signals' => [[
                'key' => 'category_fit',
                'label' => 'Category fit',
                'weight' => 0.25,
                'score' => 0.9,
                'reason' => 'Run clubs and cafés collaborate often.',
            ]],
            'suggested_format' => [
                'title' => 'Sunday morning run + coffee',
                'intent_type' => 'product_promotion',
                'weekday' => 'sunday',
                'time_of_day' => '09:00',
                'expected_attendance' => 40,
                'offer' => ['food_drink'],
                'expects' => ['social_media'],
            ],
            'evidence' => ['event_ids' => [], 'collaboration_ids' => []],
            'batch_key' => now()->toDateString(),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function forCommunityAudience(): static
    {
        return $this->state(fn (): array => [
            'audience' => SuggestionAudience::Community,
            'viewer_profile_id' => Profile::factory()->community(),
            'counterpart_profile_id' => Profile::factory()->business(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['dismissed_at' => now()->subDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }
}
