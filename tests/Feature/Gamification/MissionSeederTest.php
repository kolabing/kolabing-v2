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

    public function test_only_curated_v1_missions_are_app_visible(): void
    {
        $this->seed(\Database\Seeders\SystemChallengeSeeder::class);

        $visibleSlugs = \App\Models\Challenge::query()
            ->where('is_system', true)
            ->where('app_visible', true)
            ->pluck('slug')
            ->sort()
            ->values()
            ->all();

        $expected = [
            'attendee-attend-3-events-monthly',
            'attendee-complete-profile',
            'attendee-first-checkin',
            'attendee-first-review',
            'attendee-join-2-communities',
            'business-complete-profile',
            'business-first-application-accepted',
            'business-first-application-received',
            'business-first-kolab-completed',
            'business-publish-first-kolab',
            'business-receive-5-reviews',
            'business-upload-profile-photo',
            'community-apply-first-kolab',
            'community-complete-profile',
            'community-first-kolab-completed',
            'community-get-accepted-first-kolab',
            'community-refer-first-business',
            'community-upload-profile-photo',
        ];
        sort($expected);

        $this->assertSame($expected, $visibleSlugs);
    }

    /**
     * Hard product invariant (explicit user instruction): a mission must never
     * be app_visible unless its trigger is actually wired/live. An app_visible
     * mission with an inert trigger can never progress, which is exactly what
     * users must never see.
     */
    public function test_every_app_visible_mission_has_a_live_trigger(): void
    {
        $this->seed(\Database\Seeders\SystemChallengeSeeder::class);

        $nonLiveVisible = \App\Models\Challenge::query()
            ->where('is_system', true)
            ->where('app_visible', true)
            ->get()
            ->reject(fn (\App\Models\Challenge $challenge): bool => $challenge->trigger_action?->isLive() === true)
            ->pluck('slug')
            ->all();

        $this->assertSame([], $nonLiveVisible, 'app_visible missions with a non-live trigger: '.implode(', ', $nonLiveVisible));
    }
}
