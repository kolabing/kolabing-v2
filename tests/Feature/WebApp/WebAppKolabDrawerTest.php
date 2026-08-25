<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Explore as an agenda, and the Kolab drawer that opens beside it.
 *
 * The panel is Blade + Alpine with no build step, so the behaviour under test ships
 * as source inside the page — the same way WebAppPublicHandoffTest pins the login
 * bounce. These assertions therefore quote the shipped expressions. That is not a
 * proxy for the behaviour, it *is* the artifact the browser runs; what it buys is a
 * guard against the specific regressions that are easy to reintroduce by hand.
 */
class WebAppKolabDrawerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    private function feed(): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().'/feed')->assertOk();
    }

    // ── The agenda ───────────────────────────────────────────────────────

    public function test_explore_is_grouped_by_date_rather_than_laid_out_as_a_grid(): void
    {
        $this->feed()
            ->assertSee('x-for="g in groups"', false)
            ->assertSee('x-for="cd in g.cards"', false)
            // The old auto-fill grid is gone; a wall of equal cards carried no ordering.
            ->assertDontSee('repeat(auto-fill, minmax(260px, 1fr))', false);
    }

    /** Kolabs with no fixed window are actionable today, so they sit above next month's dates. */
    public function test_kolabs_with_no_fixed_window_are_grouped_first_as_open_now(): void
    {
        $this->feed()
            ->assertSee("key: 'anytime', label: t('feed.group_open')", false)
            ->assertSee('if (!cd.soonest) { anytime.push(cd); continue; }', false);
    }

    public function test_the_rail_groups_on_the_soonest_bookable_date(): void
    {
        $this->feed()->assertSee('window.kbNextDates(dates, 1)[0] || null', false);
    }

    /** The card leads with what is on offer; the poster moved to a "By …" line under it. */
    public function test_the_card_leads_with_the_offer_not_the_poster(): void
    {
        $this->feed()
            ->assertSee('x-text="cd.offer"', false)
            ->assertSee('x-text="cd.host"', false);
    }

    // ── One definition of a bookable date ────────────────────────────────

    /**
     * The rule is wrong in three quiet ways if re-derived: today is not bookable,
     * recurring_days is ISO 1-7 while Date.getDay() is 0-6 (so a mix-up is wrong only
     * on Sundays), and an empty list means "any day". It lives in one place.
     */
    public function test_the_bookable_date_rule_has_exactly_one_definition(): void
    {
        $feed = $this->feed();

        // Defined once, in the shared layout…
        $feed->assertSee('window.kbNextDates = function (kolab, limit)', false);

        // …and the apply picker delegates to it instead of carrying its own copy.
        $feed->assertSee('get dateChips() { return window.kbNextDates(this.dk, 8); }', false);
        $feed->assertDontSee('const dow = cur.getDay() === 0 ? 7 : cur.getDay();', false);
    }

    public function test_the_shared_rule_uses_iso_weekdays_and_cannot_offer_today(): void
    {
        $this->feed()
            ->assertSee('const iso = cur.getDay() === 0 ? 7 : cur.getDay();', false)
            ->assertSee('if (isNaN(cur) || cur < tomorrow) cur = tomorrow;', false);
    }

    // ── The drawer ───────────────────────────────────────────────────────

    public function test_the_detail_panel_is_a_right_hand_drawer_not_a_centred_modal(): void
    {
        $this->feed()
            ->assertSee('fixed inset-y-0 right-0', false)
            ->assertSee('role="dialog" aria-modal="true"', false);
    }

    public function test_the_drawer_closes_on_escape_and_on_the_scrim(): void
    {
        $this->feed()
            ->assertSee('@keydown.escape.window="closeDetail()"', false)
            ->assertSee('@click="closeDetail()"', false);
    }

    /** A page that scrolls under an open drawer loses the reader's place in the feed. */
    public function test_opening_the_drawer_holds_the_list_still_and_closing_releases_it(): void
    {
        $this->feed()
            ->assertSee("lockScroll(on) { document.body.style.overflow = on ? 'hidden' : ''; }", false)
            ->assertSee('closeDetail() { this.dk = null; this.detailError = \'\'; this.lockScroll(false); }', false)
            // The success sheet also dismisses the drawer, so it releases the lock too.
            ->assertSee('closeSuccess() { this.applySuccess = false; this.dk = null; this.lockScroll(false); }', false);
    }

    public function test_the_arrows_walk_the_list_in_place(): void
    {
        $this->feed()
            ->assertSee('x-if="neighbourIds.length > 1"', false)
            ->assertSee('openNeighbour(-1)', false)
            ->assertSee('openNeighbour(1)', false);
    }

    /** No list to walk on the single-Kolab page, so the arrows are absent rather than dead. */
    public function test_the_arrows_are_absent_where_there_is_no_list(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee('get neighbourIds() { return Array.isArray(this.cards) ? this.cards.map(c => c.id) : []; }', false);
    }

    /**
     * A published Kolab has a page on the open web that opens without an account, so
     * that is the link worth sharing. A draft's public page 404s by design (ROLES
     * §4.3), so sharing it can only mean the in-app URL.
     */
    public function test_copy_link_shares_the_public_url_only_when_there_is_one(): void
    {
        $this->feed()
            ->assertSee("(k.status === 'published' && marketing)", false)
            ->assertSee("? marketing + '/kolabs/' + k.id", false)
            ->assertSee(": location.origin + window.kbPath('/kolabs/' + k.id)", false);
    }

    /** The Clipboard API needs a secure origin; the button still works over plain http. */
    public function test_copy_link_has_a_fallback_for_insecure_origins(): void
    {
        $this->feed()
            ->assertSee('navigator.clipboard?.writeText', false)
            ->assertSee("document.execCommand('copy')", false);
    }

    // ── What the drawer says ─────────────────────────────────────────────

    /** Which side of the trade a chip sits on is the whole point, so they are two lists. */
    public function test_the_drawer_separates_what_is_offered_from_what_is_wanted(): void
    {
        $this->feed()
            ->assertSee('x-for="ch in dkGives"', false)
            ->assertSee('x-for="ch in dkWants"', false)
            ->assertSee(__('webapp.detail.on_offer'))
            ->assertSee(__('webapp.detail.looking_for'));
    }

    /** The columns swap with the intent: a community offers in return, a venue offers outright. */
    public function test_the_two_lists_follow_the_kolabs_intent(): void
    {
        $this->feed()
            ->assertSee("k.intent_type === 'community_seeking' ? k.offers_in_return : k.offering", false)
            ->assertSee('? k.needs', false);
    }

    /**
     * Production stores these as lists of slugs; KolabFactory still writes the older
     * associative-boolean map (BACKLOG BE-FX-25). Both must render.
     */
    public function test_offer_lists_accept_both_shapes_that_exist_in_the_data(): void
    {
        $this->feed()
            ->assertSee('if (Array.isArray(raw)) {', false)
            ->assertSee('items = Object.keys(raw).filter(key => !!raw[key]);', false);
    }

    public function test_the_action_card_says_where_the_viewer_stands_before_offering_a_button(): void
    {
        $this->feed()
            ->assertSee('x-text="dkActionTitle"', false)
            ->assertSee('x-text="dkActionBody"', false)
            // Owner, applicant, closed, open — four different things to say.
            ->assertSee(__('webapp.detail.your_kolab'))
            ->assertSee(__('webapp.detail.applied_title'))
            ->assertSee(__('webapp.detail.not_open'));
    }

    public function test_the_drawer_still_carries_the_owner_controls(): void
    {
        $this->feed()
            ->assertSee('publishKolab()', false)
            ->assertSee("kbPath('/kolabs/' + dk.id + '/edit')", false);
    }

    /** Motion is decoration; someone who asked their OS for less of it gets less. */
    public function test_the_drawers_motion_respects_a_reduced_motion_preference(): void
    {
        $this->feed()
            ->assertSee('motion-reduce:transition-none', false)
            ->assertSee('@media (prefers-reduced-motion: reduce)', false);
    }
}
