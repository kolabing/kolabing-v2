<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabGoalHighlightsColumnTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_goal_and_highlights_persist(): void
    {
        $profile = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->for($profile, 'creatorProfile')->create([
            'goal' => 'more_visits',
            'highlights' => ['good_location', 'free_samples'],
        ]);

        $kolab->refresh();

        $this->assertSame('more_visits', $kolab->goal);
        $this->assertSame(['good_location', 'free_samples'], $kolab->highlights);
    }

    public function test_goal_and_highlights_are_nullable(): void
    {
        $profile = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->for($profile, 'creatorProfile')->create([
            'goal' => null,
            'highlights' => null,
        ]);

        $kolab->refresh();

        $this->assertNull($kolab->goal);
        $this->assertNull($kolab->highlights);
    }
}
