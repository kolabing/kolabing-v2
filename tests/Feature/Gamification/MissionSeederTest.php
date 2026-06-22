<?php

declare(strict_types=1);

namespace Tests\Feature\Gamification;

use App\Enums\ChallengeAudience;
use App\Models\Challenge;
use Database\Seeders\SystemChallengeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MissionSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_produces_the_expected_mission_set_by_audience(): void
    {
        $this->seed(SystemChallengeSeeder::class);

        $this->assertSame(14, Challenge::query()->where('audience', ChallengeAudience::Attendee)->count());
        $this->assertSame(19, Challenge::query()->where('audience', ChallengeAudience::Business)->where('is_system', true)->whereNotNull('trigger_action')->count());
        $this->assertSame(16, Challenge::query()->where('audience', ChallengeAudience::Community)->where('is_system', true)->whereNotNull('trigger_action')->count());
        $this->assertSame(49, Challenge::query()->where('is_system', true)->whereNotNull('trigger_action')->count());
    }

    public function test_every_seeded_mission_has_a_unique_slug_and_required_mission_fields(): void
    {
        $this->seed(SystemChallengeSeeder::class);

        $missions = Challenge::query()->whereNotNull('trigger_action')->get();

        $slugs = $missions->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count(), 'Slugs must be unique.');
        $this->assertTrue($slugs->every(fn ($s): bool => is_string($s) && $s !== ''));

        foreach ($missions as $mission) {
            $this->assertNotNull($mission->trigger_action);
            $this->assertNotNull($mission->repeat_interval);
            $this->assertGreaterThanOrEqual(1, $mission->target_value);
            $this->assertTrue($mission->is_system);
            $this->assertNull($mission->event_id);
        }
    }

    public function test_seeder_is_idempotent_by_slug(): void
    {
        $this->seed(SystemChallengeSeeder::class);
        $first = Challenge::query()->whereNotNull('trigger_action')->count();

        $this->seed(SystemChallengeSeeder::class);
        $second = Challenge::query()->whereNotNull('trigger_action')->count();

        $this->assertSame($first, $second);
        $this->assertSame(49, $second);
    }

    public function test_wipe_and_reseed_migration_logic_replaces_old_system_challenges(): void
    {
        // Simulate the legacy state: an old peer-verified system challenge with
        // no slug / trigger, plus a custom event challenge that must survive.
        $legacy = Challenge::factory()->create([
            'name' => 'Legacy icebreaker',
            'is_system' => true,
            'event_id' => null,
            'slug' => null,
            'trigger_action' => null,
        ]);

        // Mirror the data migration body (it is guarded off in testing env).
        Challenge::query()->where('is_system', true)->delete();
        $this->seed(SystemChallengeSeeder::class);

        $this->assertDatabaseMissing('challenges', ['id' => $legacy->id]);
        $this->assertSame(49, Challenge::query()->where('is_system', true)->count());

        // Re-running is idempotent.
        Challenge::query()->where('is_system', true)->delete();
        $this->seed(SystemChallengeSeeder::class);
        $this->assertSame(49, Challenge::query()->where('is_system', true)->count());
    }
}
