<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\Profile;
use Database\Seeders\PeerChallengeLibrarySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The library has content (kolabing-app#150).
 *
 * #150 built the shelf and left it empty: on the development database,
 * `challenges where is_system and trigger_action is null` — the query that IS
 * the library — returned **zero rows**, because all 49 seeded system challenges
 * are trigger-driven missions. A leader opening the curation screen was told
 * there was nothing to choose from, and a community that had curated nothing
 * fell back to a library of nothing.
 */
class PeerChallengeLibrarySeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_fills_the_library_the_endpoint_reads(): void
    {
        $this->seed(PeerChallengeLibrarySeeder::class);

        // The exact query ChallengeService::library() runs.
        $library = Challenge::query()
            ->where('is_system', true)
            ->whereNull('trigger_action')
            ->count();

        $this->assertGreaterThanOrEqual(8, $library);
    }

    /**
     * A trigger would make it a mission the system progresses, and it would
     * disappear from every event surface.
     */
    public function test_nothing_in_it_is_trigger_driven_or_event_scoped(): void
    {
        $this->seed(PeerChallengeLibrarySeeder::class);

        $rows = Challenge::query()
            ->where('is_system', true)
            ->whereNull('trigger_action')
            ->get();

        foreach ($rows as $row) {
            $this->assertNull($row->trigger_action);
            $this->assertNull($row->event_id, 'a library challenge belongs to no single event');
            $this->assertTrue($row->is_system);
        }
    }

    /**
     * `proof_type` is per challenge on purpose: a selfie wants the camera, a
     * conversation does not.
     */
    public function test_it_uses_both_engines(): void
    {
        $this->seed(PeerChallengeLibrarySeeder::class);

        $types = Challenge::query()
            ->where('is_system', true)
            ->whereNull('trigger_action')
            ->pluck('proof_type')
            ->map(fn ($t) => $t instanceof \App\Enums\ChallengeProofType ? $t->value : $t)
            ->unique();

        $this->assertContains('photo', $types);
        $this->assertContains('text', $types);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(PeerChallengeLibrarySeeder::class);
        $first = Challenge::query()->whereNull('trigger_action')->where('is_system', true)->count();

        $this->seed(PeerChallengeLibrarySeeder::class);
        $second = Challenge::query()->whereNull('trigger_action')->where('is_system', true)->count();

        $this->assertSame($first, $second);
    }

    public function test_the_endpoint_serves_it(): void
    {
        $this->seed(PeerChallengeLibrarySeeder::class);

        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $names = collect(
            $this->actingAs($profile)
                ->getJson('/api/v1/challenge-library?limit=100')
                ->assertSuccessful()
                ->json('data.challenges')
        )->pluck('name');

        $this->assertContains('Take a selfie together', $names);
    }
}
