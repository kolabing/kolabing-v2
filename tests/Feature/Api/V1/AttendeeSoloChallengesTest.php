<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\MissionTrigger;
use App\Models\AttendeeProfile;
use App\Models\Profile;
use Database\Seeders\SystemChallengeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Challenges nobody has to confirm (kolabing-app#158, §5).
 *
 * > If it's "Attend 3 events this month" → Kolabing can verify that
 * > automatically from check-ins. So we shouldn't force every challenge through
 * > the same QR-confirmation mechanic.
 *
 * The machinery already existed — trigger-driven challenges progressed by
 * `MissionService` — and the gap was who could see it: `/missions` was reachable
 * from the business and community profiles and from nowhere an attendee could
 * get to, which is the one role §5's examples are about.
 *
 * These tests hold the API end of that: an attendee's request returns attendee
 * missions with progress, and does not hand them another role's.
 */
class AttendeeSoloChallengesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    public function test_an_attendee_can_read_their_own_solo_challenges(): void
    {
        $this->seed(SystemChallengeSeeder::class);

        $response = $this->actingAs($this->attendee())
            ->getJson('/api/v1/me/missions')
            ->assertSuccessful();

        $this->assertIsArray($response->json('data'));
    }

    /**
     * The point of the surface: these are progressed by the system, so every one
     * of them carries a trigger and needs nobody's confirmation.
     */
    public function test_every_solo_challenge_is_trigger_driven(): void
    {
        $this->seed(SystemChallengeSeeder::class);

        $missions = $this->actingAs($this->attendee())
            ->getJson('/api/v1/me/missions')
            ->assertSuccessful()
            ->json('data');

        $triggers = MissionTrigger::values();

        foreach ($this->flatten($missions) as $mission) {
            $this->assertArrayHasKey('trigger_action', $mission);
            $this->assertContains(
                $mission['trigger_action'],
                $triggers,
                'a mission on this surface must be one the system can progress itself'
            );
        }
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/missions')->assertStatus(401);
    }

    /**
     * The response is grouped in places; flatten whatever shape it is so the
     * assertion above does not depend on the grouping.
     *
     * @param  mixed  $data
     * @return array<int, array<string, mixed>>
     */
    private function flatten($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $out = [];

        foreach ($data as $value) {
            if (is_array($value) && array_key_exists('trigger_action', $value)) {
                $out[] = $value;
            } elseif (is_array($value)) {
                $out = array_merge($out, $this->flatten($value));
            }
        }

        return $out;
    }
}
