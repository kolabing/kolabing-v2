<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IntentType;
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
     * `signals` and `suggested_format` mirror what SignalScorer and
     * FormatSuggester actually write — keys and raw params, never rendered
     * sentences, an ISO weekday and an `H:i` time. A fixture in any other shape
     * would let the read-side tests pass against copy that renders as an empty
     * string on a real row.
     *
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
                'reason_key' => 'category_fit',
                'reason_params' => [
                    'community_type' => 'run_club',
                    'business_category' => 'cafe',
                ],
                'weight' => 0.25,
                'score' => 0.9,
            ]],
            'suggested_format' => [
                'title_key' => 'run_club',
                'title_params' => ['community_type' => 'run_club'],
                'intent_type' => IntentType::VenuePromotion->value,
                'weekday' => 7,
                'time_of_day' => '09:00',
                'expected_attendance' => 40,
                'offer' => ['food_drink'],
                'expects' => [],
                'notes' => [],
                'attendance_basis' => 'past_events',
                'weekday_basis' => 'series',
            ],
            'evidence' => ['event_ids' => [], 'collaboration_ids' => []],
            'batch_key' => now()->toDateString(),
            'expires_at' => now()->addDays($this->expiryDays()),
        ];
    }

    public function forCommunityAudience(): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => SuggestionAudience::Community,
            'viewer_profile_id' => Profile::factory()->community(),
            'counterpart_profile_id' => Profile::factory()->business(),
            'suggested_format' => $this->format($attributes, [
                'intent_type' => IntentType::CommunitySeeking->value,
            ]),
        ]);
    }

    /**
     * The honest cold-start card: a pair matched on profile alone, so no
     * attendance, no cadence, a generic title and a note that says as much.
     * Nothing on it carries a number.
     */
    public function withoutHistory(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confidence' => SuggestionConfidence::Low,
            'suggested_format' => $this->format($attributes, [
                'title_key' => 'generic',
                'weekday' => null,
                'time_of_day' => null,
                'expected_attendance' => null,
                'notes' => [['reason_key' => 'no_history', 'reason_params' => []]],
                'attendance_basis' => 'profile_only',
                'weekday_basis' => 'none',
            ]),
        ]);
    }

    /**
     * A community that draws more people than the room holds: the capped number
     * ships, and the note carries the uncapped one so the card can name the
     * constraint.
     */
    public function cappedByVenue(int $expected = 45, int $capacity = 40): static
    {
        return $this->state(fn (array $attributes): array => [
            'suggested_format' => $this->format($attributes, [
                'expected_attendance' => $capacity,
                'notes' => [[
                    'reason_key' => 'scale_fit',
                    'reason_params' => ['expected' => $expected, 'capacity' => $capacity],
                ]],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function format(array $attributes, array $changes): array
    {
        $format = is_array($attributes['suggested_format'] ?? null)
            ? $attributes['suggested_format']
            : [];

        return array_merge($format, $changes);
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
