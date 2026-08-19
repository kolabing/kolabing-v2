<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SuggestionAudience;
use App\Enums\SuggestionConfidence;
use App\Enums\UserType;
use App\Models\Kolab;
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
            'score' => fake()->numberBetween($this->minScore(), 95),
            'confidence' => SuggestionConfidence::Medium,
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
            'expires_at' => now()->addDays($this->expiryDays()),
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

    /**
     * Address the suggestion to a specific pair, deriving `audience` from the
     * viewer's own role so the row cannot claim an audience the viewer is not.
     */
    public function forPair(Profile $viewer, Profile $counterpart): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => $viewer->user_type === UserType::Community
                ? SuggestionAudience::Community
                : SuggestionAudience::Business,
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $counterpart->id,
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['dismissed_at' => now()->subDay()]);
    }

    /**
     * A row the viewer already turned into a Kolab, which retires it.
     */
    public function converted(): static
    {
        return $this->state(fn (): array => ['converted_kolab_id' => Kolab::factory()]);
    }

    /**
     * Aged out: past its expiry, and last scored before that expiry began, so
     * the row is not the impossible "expired but scored today".
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'batch_key' => now()->subDays($this->expiryDays() + 1)->toDateString(),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Read from config so tuning the window does not silently drift fixtures
     * away from what the generator would actually write.
     */
    private function expiryDays(): int
    {
        return (int) config('suggestions.expires_after_days');
    }

    private function minScore(): int
    {
        return (int) config('suggestions.min_score');
    }
}
