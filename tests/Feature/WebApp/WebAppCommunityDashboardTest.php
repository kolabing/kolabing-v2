<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The community dashboard, brought up to the business one (BE-NF-46).
 *
 * The panel has no build step, so the behaviour ships as source inside the page and
 * these assertions quote the shipped expressions — the same approach as the other
 * WebApp tests. The mistakes worth guarding here are all "two things saying the same
 * thing differently": a Next-up card arguing with the profile-strength meter, a CTA
 * that lands on the wrong sub-tab, and a permanent "0 RECEIVED" tile.
 */
class WebAppCommunityDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    private function dashboard(): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().'/dashboard')->assertOk();
    }

    public function test_the_community_branch_now_has_a_next_up_card(): void
    {
        $this->dashboard()
            ->assertSee('x-if="d.next_action && !duplicateProfilePrompt"', false)
            ->assertSee('nextActionHref', false);
    }

    /**
     * The server's `complete_profile` and the panel's profile-strength meter are the
     * same advice measured two ways (four fields against seven) and can disagree.
     * While the meter is up, the card stands down.
     */
    public function test_the_next_up_card_stands_down_while_the_profile_meter_is_showing(): void
    {
        $this->dashboard()
            ->assertSee("this.d.next_action?.key === 'complete_profile' && this.profileScore.percent < 100", false);
    }

    /** A community that posted a Kolab needs the received side, not its own default. */
    public function test_the_review_applications_cta_lands_on_the_received_sub_tab(): void
    {
        $this->dashboard()
            ->assertSee("review_pending_applications: '/kolabs?tab=requests&sub=received'", false);
    }

    public function test_my_kolabs_honours_the_requested_sub_tab(): void
    {
        $this->get('http://'.$this->host().'/kolabs')
            ->assertOk()
            ->assertSee("const sub = params.get('sub');", false)
            ->assertSee("['sent', 'received'].includes(sub)", false);
    }

    /** Applying is the community's first move, so the chain has a step business does not. */
    public function test_the_community_only_next_action_has_a_destination(): void
    {
        $this->dashboard()->assertSee("apply_to_first: '/feed'", false);
    }

    /** A permanent "0 RECEIVED" would be noise for the majority who only ever apply. */
    public function test_the_received_tile_appears_only_once_there_is_something_in_it(): void
    {
        $this->dashboard()->assertSee('if ((r.total ?? 0) > 0) {', false);
    }

    // ── The community they run ───────────────────────────────────────────

    public function test_the_community_summary_reuses_the_stats_the_shell_already_fetched(): void
    {
        $this->dashboard()
            ->assertSee('x-show="communityStats && activeCommunity"', false)
            ->assertSee('communityTiles', false)
            ->assertSee('this.communityStats?.members || {}', false);
    }

    /** The shell used to throw everything but `pending` away. */
    public function test_the_shell_keeps_the_whole_community_stats_payload(): void
    {
        $this->dashboard()
            ->assertSee('communityStats: null', false)
            ->assertSee('this.communityStats = res.json?.data || null;', false);
    }

    /** Nagging a community that has not created one yet would be a second front door. */
    public function test_a_community_with_no_community_yet_is_not_nagged(): void
    {
        $this->dashboard()->assertSee('x-show="communityStats && activeCommunity"', false);
    }

    // ── next_action speaks the panel's languages ─────────────────────────

    /**
     * The API builds next_action's title and body as English prose and mobile reads
     * it that way, so the panel translates by `key` and falls back to whatever the
     * server sent — an unknown key still renders rather than vanishing.
     */
    public function test_next_action_is_translated_by_key_with_the_server_string_as_the_fallback(): void
    {
        $this->dashboard()
            ->assertSee("window.tOr('dashboard.na_' + na.key", false)
            ->assertSee('na.title)', false)
            ->assertSee('na.body)', false);
    }

    public function test_one_waiting_application_reads_differently_from_several(): void
    {
        $this->dashboard()
            ->assertSee("na.key === 'review_pending_applications' && count === 1 ? '_one' : ''", false);
    }

    /** Both dashboards use the translated getters — the business card was English-only too. */
    public function test_neither_dashboard_prints_the_raw_server_string_any_more(): void
    {
        $this->dashboard()->assertDontSee('x-text="d.next_action?.title"', false);
    }

    // ── Wording ──────────────────────────────────────────────────────────

    public function test_the_new_strings_exist_in_every_supported_locale(): void
    {
        $keys = [
            'webapp.dashboard.your_community',
            'webapp.dashboard.members',
            'webapp.dashboard.requests',
            'webapp.dashboard.received',
            'webapp.dashboard.na_apply_to_first_title',
            'webapp.dashboard.na_review_pending_applications_one_title',
        ];

        foreach (['en', 'es', 'ca'] as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame($key, trans($key, [], $locale), "{$key} is missing for {$locale}");
            }
        }
    }

    public function test_no_untranslated_dashboard_keys_leak_into_the_page(): void
    {
        $this->dashboard()->assertDontSee('webapp.dashboard.');
    }
}
