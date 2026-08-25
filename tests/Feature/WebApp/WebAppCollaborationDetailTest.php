<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Running a collaboration from the panel (BE-NF-45).
 *
 * The panel is Blade + Alpine with no build step, so the behaviour ships as source
 * inside the page and these assertions quote the shipped expressions — the same
 * approach as WebAppKolabDrawerTest and WebAppPublicHandoffTest. What they buy is a
 * guard against the specific mistakes that are easy to make by hand here: offering a
 * button the API would refuse, flattening a lifecycle `error_code` into noise,
 * showing a community the business-only feedback fields, or letting a lapsed
 * business through the §2.8 re-gate.
 */
class WebAppCollaborationDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    private function page(): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().'/collaborations/01a0-some-uuid')->assertOk();
    }

    // ── The page exists at all ───────────────────────────────────────────

    public function test_the_collaboration_page_renders_on_the_app_host(): void
    {
        $this->page()
            ->assertSee('collaborationPage()', false)
            ->assertSee("api('/collaborations/' + this.id)", false);
    }

    /** It is a panel page, so it carries the shell and bounces a guest to login. */
    public function test_the_page_requires_a_session_and_keeps_the_shell(): void
    {
        $this->page()
            ->assertSee('kbMerge(kbShell(), collaborationPage())', false)
            ->assertSee('window.kb.requireAuth()', false);
    }

    /** The id comes off the path, so /collaborations/{id} is the only source of truth. */
    public function test_the_id_is_read_from_the_url(): void
    {
        $this->page()->assertSee("location.pathname.slice((window.KB_BASE || '').length).split('/')[2]", false);
    }

    // ── The lifecycle, all four transitions ──────────────────────────────

    public function test_every_lifecycle_endpoint_is_wired(): void
    {
        $this->page()
            ->assertSee("this.act('/activate')", false)
            ->assertSee("this.act('/complete', {})", false)
            ->assertSee("this.act('/cancel', { reason: this.cancelReason.trim() })", false)
            ->assertSee("this.act('/completion', body)", false)
            ->assertSee("'/collaborations/' + this.id + '/review'", false)
            ->assertSee("'/collaborations/' + this.id + '/feedback'", false);
    }

    /**
     * The buttons follow the server's own answer. Deriving them from the status
     * string instead would offer, for example, "start" on a collaboration the API
     * has already moved on from.
     */
    public function test_the_buttons_come_from_the_servers_actions_block_not_from_the_status(): void
    {
        $this->page()
            ->assertSee('this.c?.actions?.can_activate', false)
            ->assertSee('this.c?.actions?.can_complete', false)
            ->assertSee('this.c?.actions?.can_cancel', false);
    }

    /** Cancelling needs a reason of at least 20 characters — the API rejects less. */
    public function test_cancelling_is_blocked_until_the_reason_is_long_enough(): void
    {
        $this->page()
            ->assertSee('cancelReason.trim().length < 20', false)
            ->assertSee('20 - cancelReason.trim().length', false);
    }

    // ── The completion confirmation ──────────────────────────────────────

    /** ROLES §4: both parties answer yes/not_yet/no, and that gates /complete. */
    public function test_the_completion_question_offers_all_three_answers(): void
    {
        $this->page()
            ->assertSee("{ value: 'yes',", false)
            ->assertSee("{ value: 'not_yet',", false)
            ->assertSee("{ value: 'no',", false);
    }

    public function test_both_sides_answers_are_shown_so_the_gate_is_legible(): void
    {
        $this->page()
            ->assertSee('this.c?.own_completion?.status', false)
            ->assertSee('this.c?.partner_completion_status', false);
    }

    /**
     * The three /complete refusals are real states, not failures. Each gets its own
     * sentence — "Waiting on them" is not the same message as "answer first".
     */
    public function test_the_three_completion_gate_errors_are_translated_into_their_real_meaning(): void
    {
        $this->page()
            ->assertSee("awaiting_own_completion_confirmation: 'collab.gate_own'", false)
            ->assertSee("awaiting_partner_completion_confirmation: 'collab.gate_partner'", false)
            ->assertSee("completion_not_confirmed: 'collab.gate_not_confirmed'", false);
    }

    /**
     * The lifecycle endpoints put context in `errors` (own_status,
     * pending_completion_from) rather than validation messages, so errorText()
     * would render "no" or "completion_not_confirmed" at the user. The code is
     * read first, always.
     */
    public function test_the_error_code_is_read_before_falling_back_to_the_validation_bag(): void
    {
        $this->page()
            ->assertSee('res?.json?.error_code || res?.json?.errors?.error_code', false)
            ->assertSee('if (known) return window.t(known);', false);
    }

    // ── The review ───────────────────────────────────────────────────────

    /** The 5-star format is all-or-nothing server-side, so the button waits for all five. */
    public function test_the_review_uses_the_five_star_format_and_waits_for_every_star(): void
    {
        $this->page()
            ->assertSee('communication_rating', false)
            ->assertSee('reliability_rating', false)
            ->assertSee('fit_rating', false)
            ->assertSee('value_rating', false)
            ->assertSee('repeat_rating', false)
            ->assertSee('this.ratingRows.every(r => this.review[r.key] >= 1)', false);
    }

    public function test_a_review_that_is_already_in_is_not_offered_again(): void
    {
        $this->page()
            ->assertSee('x-if="!c.has_reviewed"', false)
            ->assertSee('x-if="c.has_reviewed"', false);
    }

    // ── The private impact numbers ───────────────────────────────────────

    /**
     * `stories_posted` / `revenue` are `prohibited` for a community and `benefits`
     * is `prohibited` for a business, so offering the wrong pair guarantees a 422.
     */
    public function test_the_impact_fields_are_role_shaped_on_both_the_form_and_the_payload(): void
    {
        $this->page()
            ->assertSee('x-if="isBusiness"', false)
            ->assertSee('x-if="!isBusiness"', false)
            ->assertSee('if (this.isBusiness) {', false)
            ->assertSee('body.stories_posted', false)
            ->assertSee('body.benefits', false);
    }

    /** Editing is allowed only until the partner submits — after that the API locks both rows. */
    public function test_editing_the_numbers_disappears_once_the_partner_has_submitted(): void
    {
        $this->page()
            ->assertSee('x-if="c.own_feedback && !c.partner_feedback"', false)
            ->assertSee('x-if="c.own_feedback && c.partner_feedback"', false)
            ->assertSee("method: editing ? 'PUT' : 'POST'", false);
    }

    // ── ROLES §2.8, the one-sided re-gate ────────────────────────────────

    /**
     * A lapsed business loses its ongoing collaborations until it resubscribes; the
     * community counterparty never does. The flag is computed server-side
     * (CollaborationResource::viewerMustResubscribe) precisely so the client cannot
     * get the role logic wrong — so the page must branch on it, not on user_type.
     */
    public function test_a_lapsed_business_is_re_gated_on_the_servers_flag_alone(): void
    {
        $this->page()
            ->assertSee('c.viewer_must_resubscribe', false)
            ->assertSee('x-if="!loading && c && !c.viewer_must_resubscribe"', false)
            ->assertSee(__('webapp.collab.regate_title'));
    }

    /** The gate is a prompt to resubscribe, not a dead end. */
    public function test_the_re_gate_points_at_the_plan_page(): void
    {
        $this->page()->assertSee("window.kbPath('/subscription')", false);
    }

    // ── Getting there ────────────────────────────────────────────────────

    public function test_my_kolabs_rows_link_into_the_collaboration(): void
    {
        $this->get('http://'.$this->host().'/kolabs')
            ->assertOk()
            ->assertSee("window.kbPath('/collaborations/' + cl.id)", false);
    }

    public function test_the_dashboards_upcoming_cards_link_into_the_collaboration(): void
    {
        $this->get('http://'.$this->host().'/dashboard')
            ->assertOk()
            ->assertSee("window.kbPath('/collaborations/' + c.id)", false);
    }

    // ── Wording ──────────────────────────────────────────────────────────

    /** A missing key renders as `webapp.collab.…`, which is worse than a wrong word. */
    public function test_no_untranslated_keys_leak_into_the_page(): void
    {
        $this->page()->assertDontSee('webapp.collab.');
    }

    public function test_the_page_is_translated_in_every_supported_locale(): void
    {
        foreach (['en', 'es', 'ca'] as $locale) {
            $this->assertIsString(
                trans('webapp.collab.confirm_title', [], $locale),
                "webapp.collab is missing for {$locale}",
            );
            $this->assertNotSame(
                'webapp.collab.confirm_title',
                trans('webapp.collab.confirm_title', [], $locale),
                "webapp.collab.confirm_title is not translated for {$locale}",
            );
        }
    }
}
