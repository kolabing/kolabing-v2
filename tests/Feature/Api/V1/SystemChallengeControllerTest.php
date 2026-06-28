<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeAudience;
use App\Enums\MissionTrigger;
use App\Models\Challenge;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemChallengeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_system_challenges_excludes_general_missions(): void
    {
        $profile = Profile::factory()->business()->create();

        $eventChallenge = Challenge::factory()->create([
            'is_system' => true,
            'event_id' => null,
            'trigger_action' => null,
        ]);

        Challenge::factory()->create([
            'is_system' => true,
            'event_id' => null,
            'audience' => ChallengeAudience::Business,
            'trigger_action' => MissionTrigger::KolabPublished,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/challenges/system')
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($eventChallenge->id, $ids);
        $this->assertCount(1, $ids);
    }
}
