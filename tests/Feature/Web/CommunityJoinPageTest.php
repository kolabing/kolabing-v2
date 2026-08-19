<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityJoinPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_join_page_renders_for_a_known_slug(): void
    {
        $community = Community::factory()->create([
            'name' => 'Barcelona Run Club',
            'slug' => 'barcelona-run-club',
            'description' => 'We run every Tuesday.',
        ]);
        CommunityMember::factory()->count(3)->create(['community_id' => $community->id]);

        $this->get('/c/barcelona-run-club')
            ->assertOk()
            ->assertSee('Barcelona Run Club')
            ->assertSee('We run every Tuesday.')
            ->assertSee('3 members');
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get('/c/does-not-exist')->assertNotFound();
    }

    public function test_the_invite_url_the_backend_hands_out_now_resolves(): void
    {
        // Community::inviteUrl() has always emitted this path; it used to 404.
        $community = Community::factory()->create();

        $path = parse_url($community->inviteUrl(), PHP_URL_PATH);

        $this->get($path)->assertOk();
    }

    public function test_removed_members_are_not_counted(): void
    {
        $community = Community::factory()->create(['slug' => 'count-check']);
        CommunityMember::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'status' => CommunityMemberStatus::Removed->value,
        ]);

        $this->get('/c/count-check')->assertOk()->assertSee('1 member');
    }

    public function test_the_tier_ladder_renders_highest_rank_first(): void
    {
        $community = Community::factory()->create(['slug' => 'tiers-check']);
        CommunityTier::factory()->forCommunity($community)->create(['name' => 'Pledge', 'rank' => 1]);
        CommunityTier::factory()->forCommunity($community)->create(['name' => 'Exec', 'rank' => 5]);

        $content = $this->get('/c/tiers-check')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'Pledge'),
            strpos($content, 'Exec'),
            'The highest-ranked tier should render first.',
        );
    }

    public function test_only_this_communitys_upcoming_public_events_render(): void
    {
        $community = Community::factory()->create(['slug' => 'events-check']);

        Event::factory()->create([
            'community_id' => $community->id,
            'name' => 'Tuesday Run',
            'event_date' => now()->addWeek(),
            'visibility' => 'public',
        ]);
        Event::factory()->create([
            'community_id' => $community->id,
            'name' => 'Members Only Social',
            'event_date' => now()->addWeek(),
            'visibility' => 'members',
        ]);
        Event::factory()->create([
            'community_id' => $community->id,
            'name' => 'Last Month Run',
            'event_date' => now()->subMonth(),
            'visibility' => 'public',
        ]);
        Event::factory()->create([
            'name' => 'Someone Elses Event',
            'event_date' => now()->addWeek(),
            'visibility' => 'public',
        ]);

        $this->get('/c/events-check')
            ->assertOk()
            ->assertSee('Tuesday Run')
            ->assertDontSee('Members Only Social')
            ->assertDontSee('Last Month Run')
            ->assertDontSee('Someone Elses Event');
    }

    public function test_an_invite_only_community_is_noindex_and_an_open_one_is_not(): void
    {
        Community::factory()->inviteOnly()->create(['slug' => 'private-one']);
        Community::factory()->create(['slug' => 'open-one']);

        $this->get('/c/private-one')->assertOk()->assertSee('noindex,nofollow', false);
        $this->get('/c/open-one')->assertOk()->assertDontSee('noindex', false);
    }

    public function test_the_invitation_token_reaches_the_cta_state(): void
    {
        Community::factory()->create(['slug' => 'token-check']);

        $this->get('/c/token-check?i=abc123')
            ->assertOk()
            ->assertSee('abc123', false)
            ->assertSee('Accept invitation');
    }

    public function test_an_open_community_offers_join_and_an_invite_only_one_offers_a_request(): void
    {
        Community::factory()->create(['slug' => 'cta-open']);
        Community::factory()->inviteOnly()->create(['slug' => 'cta-private']);

        $this->get('/c/cta-open')->assertOk()->assertSee('Sign in to join');
        $this->get('/c/cta-private')->assertOk()->assertSee('Request to join');
    }
}
