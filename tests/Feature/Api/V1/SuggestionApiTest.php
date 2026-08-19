<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read side of BE-NF-28. Two rules dominate this file and are asserted from
 * both directions rather than once:
 *
 * 1. IDOR — a suggestion names who was matched with whom, so a row may only ever
 *    be read or dismissed by the profile in `viewer_profile_id`.
 * 2. Blur, never block — a non-subscribed business sees the counterpart's name
 *    and avatar masked and *everything else* (ROLES-AND-PERMISSIONS.md §2.4,
 *    §2.5, §2.7). A community is never masked, in either direction: there is no
 *    community paywall, and this is not a third business paywall either.
 */
class SuggestionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['suggestions.enabled' => true]);
    }

    private function business(bool $subscribed = false, string $name = 'Cafe Central'): Profile
    {
        $profile = Profile::factory()->business()->create([
            'avatar_url' => 'https://cdn.test/business-avatar.png',
        ]);

        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
        ]);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);
        }

        return $profile->fresh();
    }

    private function community(string $name = 'Barcelona Run Club'): Profile
    {
        $profile = Profile::factory()->community()->create([
            'avatar_url' => 'https://cdn.test/community-avatar.png',
        ]);

        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'community_type' => 'run_club',
        ]);

        return $profile->fresh();
    }

    private function suggestion(Profile $viewer, Profile $counterpart, array $attributes = []): KolabSuggestion
    {
        return KolabSuggestion::factory()
            ->forPair($viewer, $counterpart)
            ->create($attributes);
    }

    /*
    |--------------------------------------------------------------------------
    | IDOR — written first, because it is the top risk on this surface
    |--------------------------------------------------------------------------
    */

    public function test_reading_someone_elses_suggestion_is_forbidden(): void
    {
        $owner = $this->business(subscribed: true);
        $intruder = $this->business(subscribed: true, name: 'Nosy Bakery');
        $suggestion = $this->suggestion($owner, $this->community());

        $this->actingAs($intruder)
            ->getJson(route('api.v1.suggestions.show', $suggestion))
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNull($suggestion->fresh()->clicked_at);
    }

    public function test_dismissing_someone_elses_suggestion_is_forbidden(): void
    {
        $owner = $this->business(subscribed: true);
        $intruder = $this->business(subscribed: true, name: 'Nosy Bakery');
        $suggestion = $this->suggestion($owner, $this->community());

        $this->actingAs($intruder)
            ->postJson(route('api.v1.suggestions.dismiss', $suggestion))
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNull($suggestion->fresh()->dismissed_at);
    }

    public function test_the_list_never_carries_another_profiles_rows(): void
    {
        $viewer = $this->business(subscribed: true);
        $other = $this->business(subscribed: true, name: 'Someone Else');

        $mine = $this->suggestion($viewer, $this->community('Mine'));
        $theirs = $this->suggestion($other, $this->community('Theirs'));

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk();

        $this->assertSame([$mine->id], $this->ids($response->json('data.data')));
        $this->assertNotContains($theirs->id, $this->ids($response->json('data.data')));
    }

    /*
    |--------------------------------------------------------------------------
    | Blur, never block
    |--------------------------------------------------------------------------
    */

    public function test_non_subscribed_business_sees_the_counterpart_identity_blurred(): void
    {
        $viewer = $this->business();
        $suggestion = $this->suggestion($viewer, $this->community());

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.is_identity_blurred', true)
            ->assertJsonPath('data.data.0.counterpart.name', null)
            ->assertJsonPath('data.data.0.counterpart.avatar_url', null)
            ->assertJsonPath('data.data.0.score', $suggestion->score);
    }

    public function test_subscribed_business_sees_the_counterpart_identity(): void
    {
        $viewer = $this->business(subscribed: true);
        $counterpart = $this->community('Barcelona Run Club');
        $this->suggestion($viewer, $counterpart);

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.is_identity_blurred', false)
            ->assertJsonPath('data.data.0.counterpart.name', 'Barcelona Run Club')
            ->assertJsonPath('data.data.0.counterpart.avatar_url', $counterpart->avatar_url);
    }

    public function test_community_identity_is_never_blurred_for_a_community_viewer(): void
    {
        $viewer = $this->community('Barcelona Run Club');
        $counterpart = $this->business(name: 'Cafe Central');

        KolabSuggestion::factory()
            ->forCommunityAudience()
            ->forPair($viewer, $counterpart)
            ->create();

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.is_identity_blurred', false)
            ->assertJsonPath('data.data.0.counterpart.name', 'Cafe Central')
            ->assertJsonPath('data.data.0.counterpart.avatar_url', $counterpart->avatar_url);
    }

    public function test_a_community_viewer_is_not_masked_even_though_it_has_no_subscription(): void
    {
        $viewer = $this->community();

        $this->assertFalse($viewer->hasActiveSubscription());

        KolabSuggestion::factory()
            ->forCommunityAudience()
            ->forPair($viewer, $this->business(name: 'Unmasked Bakery'))
            ->create();

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.show', KolabSuggestion::query()->sole()))
            ->assertOk()
            ->assertJsonPath('data.is_identity_blurred', false)
            ->assertJsonPath('data.counterpart.name', 'Unmasked Bakery');
    }

    public function test_blurred_payload_still_carries_score_signals_and_format(): void
    {
        $viewer = $this->business();
        $suggestion = $this->suggestion($viewer, $this->community(), [
            'score' => 78,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.is_identity_blurred', true)
            ->assertJsonPath('data.data.0.score', 78)
            ->assertJsonPath('data.data.0.confidence', $suggestion->confidence->value)
            ->assertJsonPath('data.data.0.suggested_format.expected_attendance', 40)
            ->assertJsonStructure(['data' => ['data' => [[
                'signals' => [['key', 'label', 'reason', 'score']],
                'suggested_format' => ['title', 'intent_type', 'weekday', 'time_of_day', 'expected_attendance', 'offer', 'expects', 'notes'],
            ]]]]);

        $this->assertNotEmpty($response->json('data.data.0.signals'));
        $this->assertSame('Category fit', $response->json('data.data.0.signals.0.label'));
        $this->assertNotSame('', $response->json('data.data.0.suggested_format.title'));
    }

    /*
    |--------------------------------------------------------------------------
    | Storage shape stays internal, copy is rendered at read time
    |--------------------------------------------------------------------------
    */

    public function test_the_payload_never_leaks_the_stored_key_and_param_shape(): void
    {
        $viewer = $this->business(subscribed: true);
        $this->suggestion($viewer, $this->community());

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk();

        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringNotContainsString('reason_key', $body);
        $this->assertStringNotContainsString('reason_params', $body);
        $this->assertStringNotContainsString('title_key', $body);
        $this->assertStringNotContainsString('title_params', $body);
    }

    public function test_reasons_render_in_the_callers_locale(): void
    {
        $viewer = $this->business(subscribed: true);
        $this->suggestion($viewer, $this->community());

        App::setLocale('es');

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.signals.0.label', 'Afinidad de categoría');

        $this->assertSame(
            'Las comunidades de running y los negocios de cafetería colaboran a menudo.',
            $response->json('data.data.0.signals.0.reason')
        );
    }

    public function test_a_signal_whose_copy_no_longer_exists_is_dropped_rather_than_rendered_blank(): void
    {
        $viewer = $this->business(subscribed: true);
        $this->suggestion($viewer, $this->community(), [
            'signals' => [
                [
                    'key' => 'vibe_fit',
                    'reason_key' => 'vibe_fit_great',
                    'reason_params' => [],
                    'weight' => 0.2,
                    'score' => 0.9,
                ],
                [
                    'key' => 'location_fit',
                    'reason_key' => 'location_same_city',
                    'reason_params' => [],
                    'weight' => 0.15,
                    'score' => 1.0,
                ],
            ],
            'suggested_format' => [
                'title_key' => 'vibe_gathering',
                'title_params' => [],
                'intent_type' => 'venue_promotion',
                'weekday' => null,
                'time_of_day' => null,
                'expected_attendance' => null,
                'offer' => [],
                'expects' => [],
                'notes' => [
                    ['reason_key' => 'vibe_note', 'reason_params' => []],
                    ['reason_key' => 'no_history', 'reason_params' => []],
                ],
                'attendance_basis' => 'profile_only',
                'weekday_basis' => 'none',
            ],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk();

        $signals = $response->json('data.data.0.signals');

        $this->assertCount(1, $signals);
        $this->assertSame('location_fit', $signals[0]['key']);
        $this->assertSame('Same city.', $signals[0]['reason']);

        $this->assertNull($response->json('data.data.0.suggested_format.title'));
        $this->assertSame(
            ['No past events yet — matched on profile.'],
            $response->json('data.data.0.suggested_format.notes')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Which rows are live
    |--------------------------------------------------------------------------
    */

    public function test_business_lists_its_own_live_suggestions_ordered_by_score(): void
    {
        $viewer = $this->business(subscribed: true);

        $low = $this->suggestion($viewer, $this->community('Low'), ['score' => 50]);
        $high = $this->suggestion($viewer, $this->community('High'), ['score' => 91]);
        $middle = $this->suggestion($viewer, $this->community('Middle'), ['score' => 70]);

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3);

        $this->assertSame(
            [$high->id, $middle->id, $low->id],
            $this->ids($response->json('data.data'))
        );
    }

    public function test_expired_dismissed_and_converted_rows_are_absent(): void
    {
        $viewer = $this->business(subscribed: true);

        $live = $this->suggestion($viewer, $this->community('Live'));
        $expired = KolabSuggestion::factory()->forPair($viewer, $this->community('Expired'))->expired()->create();
        $dismissed = KolabSuggestion::factory()->forPair($viewer, $this->community('Dismissed'))->dismissed()->create();
        $converted = KolabSuggestion::factory()->forPair($viewer, $this->community('Converted'))->converted()->create();

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $ids = $this->ids($response->json('data.data'));

        $this->assertSame([$live->id], $ids);
        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($dismissed->id, $ids);
        $this->assertNotContains($converted->id, $ids);
    }

    public function test_a_suggestion_whose_counterpart_was_deactivated_is_absent(): void
    {
        $viewer = $this->business(subscribed: true);
        $gone = $this->community('Deleted Club');
        $kept = $this->suggestion($viewer, $this->community('Still Here'));
        $orphan = $this->suggestion($viewer, $gone);

        $gone->delete();

        $response = $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->assertSame([$kept->id], $this->ids($response->json('data.data')));
        $this->assertNotContains($orphan->id, $this->ids($response->json('data.data')));
    }

    public function test_the_detail_of_a_row_whose_counterpart_was_deactivated_does_not_error(): void
    {
        $viewer = $this->business(subscribed: true);
        $gone = $this->community('Deleted Club');
        $suggestion = $this->suggestion($viewer, $gone);

        $gone->delete();

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.show', $suggestion))
            ->assertOk()
            ->assertJsonPath('data.counterpart.name', null);
    }

    public function test_attendee_gets_an_empty_list(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $someoneElse = $this->business(subscribed: true);
        $this->suggestion($someoneElse, $this->community());

        $this->actingAs($attendee)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data', [])
            ->assertJsonPath('data.meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Funnel timestamps
    |--------------------------------------------------------------------------
    */

    public function test_first_serve_stamps_shown_at(): void
    {
        $viewer = $this->business(subscribed: true);
        $suggestion = $this->suggestion($viewer, $this->community());

        $this->assertNull($suggestion->shown_at);

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.data.0.shown_at', fn (?string $value): bool => $value !== null);

        $this->assertNotNull($suggestion->fresh()->shown_at);
    }

    public function test_a_second_serve_keeps_the_first_shown_at_and_stamps_in_one_statement(): void
    {
        $viewer = $this->business(subscribed: true);
        $first = $this->suggestion($viewer, $this->community('A'));
        $second = $this->suggestion($viewer, $this->community('B'));
        $third = $this->suggestion($viewer, $this->community('C'));

        $updates = 0;
        DB::listen(function ($query) use (&$updates): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'update "kolab_suggestions"')) {
                $updates++;
            }
        });

        $this->actingAs($viewer)->getJson(route('api.v1.suggestions.index'))->assertOk();

        $this->assertSame(1, $updates, 'Three unstamped rows must be stamped in ONE bulk statement, never one per row.');

        $original = $first->fresh()->shown_at;
        $this->assertNotNull($original);

        $this->travel(2)->minutes();

        $this->actingAs($viewer)->getJson(route('api.v1.suggestions.index'))->assertOk();

        $this->assertTrue($original->equalTo($first->fresh()->shown_at));
        $this->assertTrue($original->equalTo($second->fresh()->shown_at));
        $this->assertTrue($original->equalTo($third->fresh()->shown_at));
    }

    public function test_detail_stamps_clicked_at(): void
    {
        $viewer = $this->business(subscribed: true);
        $suggestion = $this->suggestion($viewer, $this->community());

        $this->assertNull($suggestion->clicked_at);

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.show', $suggestion))
            ->assertOk()
            ->assertJsonPath('data.id', $suggestion->id);

        $this->assertNotNull($suggestion->fresh()->clicked_at);
    }

    public function test_dismiss_stamps_dismissed_at_and_is_idempotent(): void
    {
        $viewer = $this->business(subscribed: true);
        $suggestion = $this->suggestion($viewer, $this->community());

        $this->actingAs($viewer)
            ->postJson(route('api.v1.suggestions.dismiss', $suggestion))
            ->assertNoContent();

        $firstDismissal = $suggestion->fresh()->dismissed_at;
        $this->assertNotNull($firstDismissal);

        $this->travel(5)->minutes();

        $this->actingAs($viewer)
            ->postJson(route('api.v1.suggestions.dismiss', $suggestion))
            ->assertNoContent();

        $this->assertTrue($firstDismissal->equalTo($suggestion->fresh()->dismissed_at));

        $this->actingAs($viewer)
            ->getJson(route('api.v1.suggestions.index'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Feature flag, route binding, query budget
    |--------------------------------------------------------------------------
    */

    public function test_endpoints_404_when_the_feature_flag_is_off(): void
    {
        config(['suggestions.enabled' => false]);

        $viewer = $this->business(subscribed: true);
        $suggestion = $this->suggestion($viewer, $this->community());

        $this->actingAs($viewer)->getJson(route('api.v1.suggestions.index'))->assertNotFound();
        $this->actingAs($viewer)->getJson(route('api.v1.suggestions.show', $suggestion))->assertNotFound();
        $this->actingAs($viewer)->postJson(route('api.v1.suggestions.dismiss', $suggestion))->assertNotFound();

        $this->assertNull($suggestion->fresh()->clicked_at);
    }

    public function test_a_non_uuid_id_never_reaches_the_database(): void
    {
        $viewer = $this->business(subscribed: true);

        $selects = 0;
        DB::listen(function ($query) use (&$selects): void {
            if (str_contains($query->sql, 'kolab_suggestions')) {
                $selects++;
            }
        });

        $this->actingAs($viewer)
            ->getJson('/api/v1/suggestions/not-a-uuid')
            ->assertNotFound();

        $this->actingAs($viewer)
            ->postJson('/api/v1/suggestions/not-a-uuid/dismiss')
            ->assertNotFound();

        $this->assertSame(0, $selects, 'A non-uuid route parameter must be rejected by the route, not sent to Postgres (22P02).');
    }

    public function test_listing_does_not_grow_queries_with_the_number_of_suggestions(): void
    {
        $viewer = $this->business(subscribed: true);
        $this->suggestion($viewer, $this->community('Only one'));

        $one = $this->countListQueries($viewer);

        foreach (['Two', 'Three', 'Four', 'Five'] as $name) {
            $this->suggestion($viewer, $this->community($name));
        }

        $this->assertSame(5, KolabSuggestion::query()->count());

        $five = $this->countListQueries($viewer);

        $this->assertSame($one, $five, "Listing 5 suggestions ran {$five} queries but 1 ran {$one} — the counterpart relations are not eager loaded.");
    }

    /**
     * Counts only the reads the listing itself performs — the suggestion rows and
     * the three counterpart tables. `update "profiles"` (the activity touch, which
     * throttles itself and so is not constant between two requests) is excluded on
     * purpose: it is not part of the listing's query budget.
     */
    private function countListQueries(Profile $viewer): int
    {
        $count = 0;

        DB::listen(function ($query) use (&$count): void {
            $sql = strtolower($query->sql);

            $touchesListing = str_contains($sql, 'kolab_suggestions')
                || str_contains($sql, 'business_profiles')
                || str_contains($sql, 'community_profiles')
                || str_contains($sql, 'from "profiles"');

            if ($touchesListing) {
                $count++;
            }
        });

        $this->actingAs($viewer)->getJson(route('api.v1.suggestions.index'))->assertOk();

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<int, string>
     */
    private function ids(?array $rows): array
    {
        return array_map(static fn (array $row): string => (string) $row['id'], $rows ?? []);
    }
}
