<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\KolabService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The end of the suggestion funnel (BE-NF-28, Task 8): `POST /api/v1/kolabs`
 * gains an optional `suggestion_id`, and a successful create writes
 * `converted_kolab_id` on that row.
 *
 * Two rules dominate this file:
 *
 * 1. **Ownership is enforced twice.** The Form Request scopes `exists` to the
 *    caller's `viewer_profile_id`, which turns a stranger's id into a clean 422
 *    instead of a silent no-op that marks someone else's row converted; and
 *    KolabService repeats the check, so the write is still safe if the rule is
 *    ever edited by someone who does not know it was load-bearing. Both halves
 *    are asserted separately — the HTTP tests cannot see the service guard,
 *    because the rule refuses the request before it is reached.
 * 2. **A suggestion must never block Kolab creation.** Creating without
 *    `suggestion_id` behaves exactly as before, and a stale-but-owned row is
 *    still marked rather than rejected.
 */
class SuggestionConversionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'intent_type' => 'community_seeking',
            'title' => 'Sunday run club looking for a coffee stop',
            'description' => 'We finish every Sunday route near Gracia and want a cafe to host the post-run coffee.',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue', 'food_drink'],
            'typical_attendance' => 40,
            'offers_in_return' => ['social_media'],
        ], $overrides);
    }

    private function suggestionFor(Profile $viewer, ?Profile $counterpart = null, array $attributes = []): KolabSuggestion
    {
        return KolabSuggestion::factory()
            ->forPair($viewer, $counterpart ?? Profile::factory()->business()->create())
            ->create($attributes);
    }

    public function test_creating_a_kolab_from_a_suggestion_marks_it_converted(): void
    {
        $community = Profile::factory()->community()->create();
        $suggestion = $this->suggestionFor($community);

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => $suggestion->id]));

        $response->assertStatus(201);

        $kolabId = $response->json('data.id');

        $this->assertNotNull($kolabId);
        $this->assertSame($kolabId, $suggestion->fresh()->converted_kolab_id);
    }

    public function test_a_suggestion_belonging_to_someone_else_is_rejected(): void
    {
        $owner = Profile::factory()->community()->create();
        $intruder = Profile::factory()->community()->create();
        $suggestion = $this->suggestionFor($owner);

        $this->actingAs($intruder)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => $suggestion->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['suggestion_id']);

        $this->assertNull($suggestion->fresh()->converted_kolab_id);
        $this->assertDatabaseCount('kolabs', 0);
    }

    public function test_an_unknown_suggestion_id_is_rejected(): void
    {
        $community = Profile::factory()->community()->create();

        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => (string) Str::uuid()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suggestion_id']);

        $this->assertDatabaseCount('kolabs', 0);
    }

    public function test_a_suggestion_id_that_is_not_a_uuid_is_rejected(): void
    {
        $community = Profile::factory()->community()->create();

        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => 'not-a-uuid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['suggestion_id']);
    }

    /**
     * The regression that matters most: the field is additive, so every existing
     * client must keep working untouched.
     */
    public function test_creating_a_kolab_without_a_suggestion_id_still_works(): void
    {
        $community = Profile::factory()->community()->create();
        $suggestion = $this->suggestionFor($community);

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.intent_type', 'community_seeking')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $community->id,
            'title' => $this->payload()['title'],
        ]);

        $this->assertNull($suggestion->fresh()->converted_kolab_id);
    }

    public function test_an_explicit_null_suggestion_id_is_accepted(): void
    {
        $community = Profile::factory()->community()->create();

        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => null]))
            ->assertStatus(201);
    }

    /**
     * Defence in depth. The Form Request already refuses a foreign id, so this
     * goes through the service directly — the only way to reach the second check
     * and therefore the only test that keeps it honest.
     */
    public function test_the_service_refuses_to_convert_another_profiles_suggestion(): void
    {
        $owner = Profile::factory()->community()->create();
        $intruder = Profile::factory()->community()->create();
        $suggestion = $this->suggestionFor($owner);

        $kolab = app(KolabService::class)->create(
            $intruder,
            $this->payload(['suggestion_id' => $suggestion->id])
        );

        $this->assertInstanceOf(Kolab::class, $kolab);
        $this->assertNull($suggestion->fresh()->converted_kolab_id);
    }

    /**
     * First conversion wins, like every other funnel timestamp on the row: a
     * second create must not overwrite which Kolab the suggestion produced.
     */
    public function test_an_already_converted_suggestion_keeps_its_first_kolab(): void
    {
        $community = Profile::factory()->community()->create();
        $firstKolab = Kolab::factory()->create(['creator_profile_id' => $community->id]);
        $suggestion = $this->suggestionFor($community, null, ['converted_kolab_id' => $firstKolab->id]);

        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => $suggestion->id]))
            ->assertStatus(201);

        $this->assertSame($firstKolab->id, $suggestion->fresh()->converted_kolab_id);
    }

    /**
     * A stale row is still worth marking, for the same reason dismissal is not
     * gated on liveness (SuggestionReader::dismiss): the suggestion did cause a
     * Kolab, and refusing the create would let an expired card break the form.
     */
    public function test_an_expired_suggestion_of_the_callers_is_still_marked_converted(): void
    {
        $community = Profile::factory()->community()->create();
        $suggestion = $this->suggestionFor($community, null, [
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->payload(['suggestion_id' => $suggestion->id]));

        $response->assertStatus(201);

        $this->assertSame($response->json('data.id'), $suggestion->fresh()->converted_kolab_id);
    }
}
