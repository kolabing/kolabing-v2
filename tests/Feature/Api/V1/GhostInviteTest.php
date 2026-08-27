<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\Encounter;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use App\Services\EncounterService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The ghost invite (#246).
 *
 * The person standing next to an attendee at an event, without the app, is the
 * most qualified prospect this product will ever have — and until now they
 * could not take part at all.
 *
 * Most of these tests are about the refusals rather than the happy path,
 * because the refusals are what keep this from being a points faucet: you must
 * be in the room, you may only hold a few at a time, and a claim only pays an
 * account that did not exist when the invite was written.
 */
class GhostInviteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function event(): Event
    {
        $host = Profile::factory()->business()->create();

        return Event::factory()->forProfile($host)->create(['is_active' => true]);
    }

    private function checkIn(Profile $profile, Event $event): void
    {
        EventCheckin::query()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'checked_in_at' => now(),
        ]);
    }

    private function service(): EncounterService
    {
        return app(EncounterService::class);
    }

    // -------------------------------------------------------------------------
    // Creating one
    // -------------------------------------------------------------------------

    public function test_an_invite_records_the_meeting_and_pays_nothing_yet(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        $before = $inviter->attendeeProfile()->first()->total_points;

        $ghost = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');

        $this->assertTrue($ghost->isGhost());
        $this->assertSame('Ana', $ghost->ghost_name);
        $this->assertSame($challenge->points, $ghost->pending_points);
        $this->assertNotNull($ghost->ghost_claim_token);
        $this->assertNotNull($ghost->expires_at);

        // The whole pull of the invite is that the reward is named and NOT paid.
        // Paying up front is what invites imaginary friends.
        $this->assertSame(
            $before,
            $inviter->attendeeProfile()->first()->total_points,
            'a ghost invite must not pay anybody until it is claimed'
        );
    }

    public function test_you_have_to_be_in_the_room(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $challenge = Challenge::factory()->system()->easy()->create();

        // Deliberately NOT checked in. Without this rule the whole mechanism is
        // a points faucet you can turn on from your sofa.
        $this->expectExceptionMessageMatches('/checked in/i');
        $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');
    }

    public function test_three_unclaimed_invites_per_event_is_the_ceiling(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        foreach (['Ana', 'Bea', 'Carla'] as $name) {
            $this->service()->createGhostInvite($inviter, $event, $challenge, $name);
        }

        try {
            $this->service()->createGhostInvite($inviter, $event, $challenge, 'Dani');
            $this->fail('a fourth unclaimed invite should have been refused');
        } catch (\App\Exceptions\ChallengeRuleException $e) {
            $this->assertSame('ghost_limit_reached', $e->reason);
        }
    }

    public function test_a_claimed_invite_frees_a_slot(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        $first = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');
        $this->service()->createGhostInvite($inviter, $event, $challenge, 'Bea');
        $this->service()->createGhostInvite($inviter, $event, $challenge, 'Carla');

        $this->service()->claim($this->attendee(), $first->ghost_claim_token);

        // The cap is on invites still WAITING, not on invites ever sent — it
        // exists to stop imaginary friends, not to ration a sociable evening.
        $fourth = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Dani');
        $this->assertNotNull($fourth->id);
    }

    public function test_a_claim_code_avoids_characters_people_misread(): void
    {
        $code = $this->service()->generateClaimCode();

        $this->assertSame(6, strlen($code));
        // Someone reads this aloud in a noisy bar or copies it from a phone held
        // at arm's length. A code that is ambiguous to read is a code that fails
        // for a reason the user cannot see.
        $this->assertDoesNotMatchRegularExpression('/[01OIL]/', $code);
    }

    // -------------------------------------------------------------------------
    // Claiming one
    // -------------------------------------------------------------------------

    public function test_claiming_pays_both_sides_what_was_promised(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        $ghost = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');
        $promised = $ghost->pending_points;

        $joiner = $this->attendee();
        $mine = $this->service()->claim($joiner, $ghost->ghost_claim_token);

        // The claimer gets their OWN row back: they want to read "you met the
        // inviter", and on the inviter's row they are the other side.
        $this->assertSame($joiner->id, $mine->profile_id);
        $this->assertSame($inviter->id, $mine->other_profile_id);

        foreach ([$inviter, $joiner] as $participant) {
            $this->assertSame(
                $promised,
                $participant->attendeeProfile()->first()->total_points
            );
        }

        $this->assertDatabaseHas('encounters', [
            'id' => $ghost->id,
            'other_profile_id' => $joiner->id,
            'pending_points' => 0,
        ]);
    }

    public function test_claiming_does_not_fabricate_a_challenge_completion(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();
        $ghost = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');

        $this->service()->claim($this->attendee(), $ghost->ghost_claim_token);

        // Nobody verified anything and the two were never checked in together.
        // A fake completion would put something that did not happen into
        // challenge stats and mission progress.
        $this->assertDatabaseCount('challenge_completions', 0);
    }

    public function test_an_account_that_already_existed_cannot_claim(): void
    {
        // The ghost path means "this person isn't on Kolabing". An account that
        // predates the invite is, by definition, not that person — and this is
        // what stops two existing users farming each other.
        $existing = $this->attendee();
        $existing->forceFill(['created_at' => now()->subYear()])->save();

        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );

        try {
            $this->service()->claim($existing->refresh(), $ghost->ghost_claim_token);
            $this->fail('an existing account should not be able to claim');
        } catch (\App\Exceptions\ChallengeRuleException $e) {
            $this->assertSame('claim_requires_new_account', $e->reason);
        }
    }

    public function test_an_expired_invite_is_refused(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );
        $ghost->update(['expires_at' => now()->subDay()]);

        try {
            $this->service()->claim($this->attendee(), $ghost->ghost_claim_token);
            $this->fail('an expired invite should not be claimable');
        } catch (\App\Exceptions\ChallengeRuleException $e) {
            $this->assertSame('claim_expired', $e->reason);
        }
    }

    public function test_an_invite_cannot_be_claimed_twice(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );

        $this->service()->claim($this->attendee(), $ghost->ghost_claim_token);

        try {
            $this->service()->claim($this->attendee(), $ghost->ghost_claim_token);
            $this->fail('a claimed invite should not be claimable again');
        } catch (\App\Exceptions\ChallengeRuleException $e) {
            $this->assertSame('invalid_claim_code', $e->reason);
        }
    }

    public function test_a_code_is_matched_case_insensitively_and_trimmed(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );

        // Someone is copying this off a screen. A stray space or a lower-case
        // paste must not be the reason they cannot join.
        $mine = $this->service()->claim(
            $this->attendee(),
            '  '.strtolower($ghost->ghost_claim_token).' '
        );

        $this->assertSame($inviter->id, $mine->other_profile_id);
    }

    public function test_you_cannot_claim_your_own_invite(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );

        try {
            $this->service()->claim($inviter, $ghost->ghost_claim_token);
            $this->fail('claiming your own invite should be refused');
        } catch (\App\Exceptions\ChallengeRuleException $e) {
            $this->assertSame('claim_self', $e->reason);
        }
    }

    // -------------------------------------------------------------------------
    // The endpoints
    // -------------------------------------------------------------------------

    public function test_the_ghost_endpoint_returns_a_code_and_an_app_host_url(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        $response = $this->actingAs($inviter)
            ->postJson('/api/v1/encounters/ghost', [
                'event_id' => $event->id,
                'challenge_id' => $challenge->id,
                'ghost_name' => 'Ana',
            ])
            ->assertCreated();

        $code = $response->json('data.claim_code');
        $this->assertNotEmpty($code);

        // The invite must point at the APP host: the association files live
        // there, and only paths on that host are handed to an installed app.
        $this->assertSame(
            rtrim((string) config('webapp.url'), '/').'/i/'.$code,
            $response->json('data.invite_url')
        );
        $this->assertSame($challenge->points, $response->json('data.encounter.pending_points'));
    }

    public function test_the_ghost_endpoint_reports_the_refusal_reason(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();

        $this->actingAs($inviter)
            ->postJson('/api/v1/encounters/ghost', [
                'event_id' => $event->id,
                'challenge_id' => Challenge::factory()->system()->easy()->create()->id,
                'ghost_name' => 'Ana',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_checked_in');
    }

    public function test_the_claim_endpoint_reports_the_refusal_reason(): void
    {
        $this->actingAs($this->attendee())
            ->postJson('/api/v1/encounters/claim', ['claim_code' => 'ZZZZZZ'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'invalid_claim_code');
    }

    public function test_a_name_is_required_but_a_contact_is_not(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        // Asking a stranger for their number at the moment you meet them is bad
        // manners and a larger data-protection surface than this needs.
        $this->actingAs($inviter)
            ->postJson('/api/v1/encounters/ghost', [
                'event_id' => $event->id,
                'challenge_id' => $challenge->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ghost_name');

        $this->actingAs($inviter)
            ->postJson('/api/v1/encounters/ghost', [
                'event_id' => $event->id,
                'challenge_id' => $challenge->id,
                'ghost_name' => 'Ana',
            ])
            ->assertCreated();
    }

    // -------------------------------------------------------------------------
    // The landing page — the half a Universal Link cannot do
    // -------------------------------------------------------------------------

    public function test_the_landing_page_shows_the_code_and_who_sent_it(): void
    {
        $inviter = $this->attendee();
        $inviter->update(['name' => 'Marta']);
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );

        $host = parse_url((string) config('webapp.url'), PHP_URL_HOST) ?: config('webapp.host');

        $this->get('http://'.$host.'/i/'.$ghost->ghost_claim_token)
            ->assertOk()
            ->assertSee($ghost->ghost_claim_token)
            ->assertSee('Marta');
    }

    public function test_an_unknown_code_renders_a_state_rather_than_a_404(): void
    {
        $host = parse_url((string) config('webapp.url'), PHP_URL_HOST) ?: config('webapp.host');

        // Someone tapping a link a fortnight late deserves to be told what
        // happened rather than shown a dead end.
        $this->get('http://'.$host.'/i/ZZZZZZ')
            ->assertOk()
            ->assertSee(__('webapp.ghost_invite.unknown_title'));
    }

    public function test_an_expired_code_says_so_on_the_page(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $ghost = $this->service()->createGhostInvite(
            $inviter,
            $event,
            Challenge::factory()->system()->easy()->create(),
            'Ana'
        );
        $ghost->update(['expires_at' => now()->subDay()]);

        $host = parse_url((string) config('webapp.url'), PHP_URL_HOST) ?: config('webapp.host');

        $this->get('http://'.$host.'/i/'.$ghost->ghost_claim_token)
            ->assertOk()
            ->assertSee(__('webapp.ghost_invite.expired_title'));
    }

    public function test_the_invite_path_is_handed_to_the_installed_app(): void
    {
        // Without /i/* in the association file the link opens a browser on every
        // phone, including the ones that have Kolabing installed.
        $this->assertContains('/i/*', config('webapp.app_links.paths'));
    }

    public function test_unclaimed_invites_are_the_only_ones_that_count(): void
    {
        $inviter = $this->attendee();
        $event = $this->event();
        $this->checkIn($inviter, $event);
        $challenge = Challenge::factory()->system()->easy()->create();

        $expired = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Ana');
        $expired->update(['expires_at' => now()->subDay()]);

        $this->service()->createGhostInvite($inviter, $event, $challenge, 'Bea');
        $this->service()->createGhostInvite($inviter, $event, $challenge, 'Carla');

        // An expired invite is gone as far as the cap is concerned: it expires
        // silently, with no notification and no penalty, and holding a slot
        // forever would be a penalty.
        $fourth = $this->service()->createGhostInvite($inviter, $event, $challenge, 'Dani');

        $this->assertNotNull($fourth->id);
        $this->assertSame(
            4,
            Encounter::query()->where('profile_id', $inviter->id)->count()
        );
    }
}
