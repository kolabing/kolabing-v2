<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityJoinPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** The page lives on the app host: only there does the CSP allow Alpine + Google. */
    private function joinPage(string $slug, string $query = ''): TestResponse
    {
        return $this->get('http://'.config('webapp.host').'/c/'.$slug.$query);
    }

    public function test_the_join_page_renders_for_a_known_slug(): void
    {
        $community = Community::factory()->create([
            'name' => 'Barcelona Run Club',
            'slug' => 'barcelona-run-club',
            'description' => 'We run every Tuesday.',
        ]);
        CommunityMember::factory()->count(3)->create(['community_id' => $community->id]);

        $this->joinPage('barcelona-run-club')
            ->assertOk()
            ->assertSee('Barcelona Run Club')
            ->assertSee('We run every Tuesday.')
            ->assertSee('3 members');
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->joinPage('does-not-exist')->assertNotFound();
    }

    /**
     * The defect this file exists to prevent: the page used to render its CTA
     * inside <template x-if> blocks on a layout that loaded no Alpine at all, so
     * a browser painted nothing. Asserting the markup is present was not enough —
     * assert the page can actually RUN it.
     */
    public function test_the_page_can_actually_run_its_alpine(): void
    {
        Community::factory()->create(['slug' => 'runnable']);

        $this->joinPage('runnable')
            ->assertOk()
            ->assertSee('alpine-3.14.1.min.js', false)
            ->assertSee('[x-cloak]', false)
            ->assertSee('communityJoinPage()', false);
    }

    public function test_the_invite_url_the_backend_hands_out_points_at_this_page(): void
    {
        $community = Community::factory()->create();

        $this->assertStringStartsWith(
            rtrim(config('webapp.url'), '/').'/c/',
            $community->inviteUrl(),
        );

        $this->joinPage($community->slug)->assertOk();
    }

    public function test_a_legacy_marketing_link_redirects_and_keeps_the_token(): void
    {
        Community::factory()->create(['slug' => 'legacy-link']);

        $this->get('http://kolabing.com/c/legacy-link?i=abc123')
            ->assertRedirect(rtrim(config('webapp.url'), '/').'/c/legacy-link?i=abc123');
    }

    public function test_a_signed_out_visitor_gets_the_google_button_and_the_form(): void
    {
        Community::factory()->create(['slug' => 'form-check']);

        $this->joinPage('form-check')
            ->assertOk()
            ->assertSee('kbGoogle', false)
            ->assertSee('Full name')
            ->assertSee('Phone number (optional)')
            ->assertSee('Add a photo');
    }

    public function test_removed_members_are_not_counted(): void
    {
        $community = Community::factory()->create(['slug' => 'count-check']);
        CommunityMember::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'status' => CommunityMemberStatus::Removed->value,
        ]);

        $this->joinPage('count-check')->assertOk()->assertSee('1 member');
    }

    public function test_the_tier_ladder_renders_highest_rank_first(): void
    {
        $community = Community::factory()->create(['slug' => 'tiers-check']);
        CommunityTier::factory()->forCommunity($community)->create(['name' => 'Pledge', 'rank' => 1]);
        CommunityTier::factory()->forCommunity($community)->create(['name' => 'Exec', 'rank' => 5]);

        $content = $this->joinPage('tiers-check')->assertOk()->getContent();

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

        $this->joinPage('events-check')
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

        $this->joinPage('private-one')->assertOk()->assertSee('noindex,nofollow', false);
        $this->joinPage('open-one')->assertOk()->assertDontSee('noindex', false);
    }

    public function test_the_invitation_token_reaches_the_cta_state(): void
    {
        Community::factory()->create(['slug' => 'token-check']);

        $this->joinPage('token-check', '?i=abc123')
            ->assertOk()
            ->assertSee('abc123', false)
            ->assertSee('Accept invitation');
    }

    public function test_the_page_is_localised(): void
    {
        Community::factory()->create(['slug' => 'locale-check']);

        $this->get('http://'.config('webapp.host').'/es/c/locale-check')
            ->assertOk()
            ->assertSee('Sobre ti');

        $this->get('http://'.config('webapp.host').'/ca/c/locale-check')
            ->assertOk()
            ->assertSee('Sobre tu');
    }
}
