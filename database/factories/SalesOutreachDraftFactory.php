<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOutreachDraft>
 */
class SalesOutreachDraftFactory extends Factory
{
    protected $model = SalesOutreachDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attendees = $this->faker->numberBetween(10, 80);
        $spend = $this->faker->numberBetween(800, 4000);

        return [
            'business_profile_id' => Profile::factory()->business(),
            'community_profile_id' => Profile::factory()->community(),
            'locale' => 'en',
            'kolab_ideas' => [[
                'title' => $this->faker->sentence(3),
                'format' => 'Weeknight evening',
                'business_provides' => 'The back room and a welcome drink',
                'community_delivers' => 'Twenty runners and stories on the night',
                'why_it_works' => 'A quiet Tuesday becomes a full room',
                'cover_image_prompt' => 'A warm cafe back room at dusk, people talking',
            ]],
            'selected_idea_index' => 0,
            'cover_image_url' => null,
            'expected_attendees' => $attendees,
            'avg_spend_cents' => $spend,
            'estimated_revenue_cents' => $attendees * $spend,
            'subject' => $this->faker->sentence(5),
            'body_markdown' => $this->faker->paragraph(),
            'status' => SalesOutreachDraft::STATUS_DRAFT,
            'generation_status' => SalesOutreachDraft::GENERATION_READY,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'status' => SalesOutreachDraft::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }
}
