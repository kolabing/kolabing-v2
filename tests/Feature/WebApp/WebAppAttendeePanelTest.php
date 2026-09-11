<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The attendee's side of app.kolabing.com.
 *
 * The requirement here was parity with the mobile app, not merely "a working web
 * flow": an attendee who signs up on a phone and one who signs up in a browser must
 * end up with the same profile, or the two clients start disagreeing about who
 * someone is. So the assertions below quote the four-step shape mobile runs
 * (`lib/features/onboarding/screens/attendee/`) and the payload its state object
 * builds — that is the contract being matched, and it is the thing that silently
 * drifts.
 *
 * The panel is Blade + Alpine with no build step, so behaviour ships as source in the
 * page; these tests read it the same way WebAppPublicHandoffTest does.
 */
class WebAppAttendeePanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    /** Named `page()`, not `get()`: TestCase::get() is public and cannot be narrowed. */
    private function page(string $path): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().$path);
    }

    // ── Register: account only ───────────────────────────────────────────

    /** The register endpoint accepts email/password/terms and nothing else. */
    public function test_registering_as_an_attendee_creates_the_account_and_hands_off(): void
    {
        $this->page('/register')
            ->assertOk()
            ->assertSee("window.kb.api('/auth/register/attendee'", false)
            ->assertSee("window.nav('/onboarding/attendee'", false);
    }

    /**
     * The old flow collected name + handle on the register page and submitted
     * onboarding inline with only those two, so a web attendee ended up with no
     * city, interests, communities or photo while a mobile one had all four.
     */
    public function test_register_no_longer_collects_the_onboarding_fields_itself(): void
    {
        $this->page('/register')
            ->assertOk()
            ->assertDontSee('register.attendee_handle_hint')
            ->assertDontSee('body: { name: f.name.trim(), handle: f.handle.trim().toLowerCase() }', false);
    }

    /** An intent that survived login must survive onboarding too. */
    public function test_the_hand_off_carries_the_visitors_original_destination(): void
    {
        $this->page('/register')
            ->assertOk()
            ->assertSee("const intended = window.kbPostAuthTarget('');", false)
            ->assertSee("'?next=' + encodeURIComponent(intended)", false);
    }

    // ── Onboarding: the same four steps as mobile ────────────────────────

    public function test_onboarding_runs_the_same_four_steps_as_the_app(): void
    {
        $page = $this->page('/onboarding/attendee')->assertOk();

        // You → City → Interests → Join.
        $page->assertSee(__('webapp.attendee_onboarding.s1_title'));
        $page->assertSee(__('webapp.attendee_onboarding.s2_title'));
        $page->assertSee(__('webapp.attendee_onboarding.s3_title'));
        $page->assertSee(__('webapp.attendee_onboarding.s4_title'));
    }

    /** Only step 1 is required; mobile lets every other step be skipped. */
    public function test_every_step_after_the_first_can_be_skipped(): void
    {
        $page = $this->page('/onboarding/attendee')->assertOk();

        $page->assertSee(__('webapp.common.skip'));
        // Skipping the Join step still submits, so the handle is claimed either way.
        $page->assertSee('@click="submit()" :disabled="busy"', false);
    }

    public function test_step_one_requires_a_name_and_an_available_handle(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee("return this.form.name.trim() !== '' && this.handleState === 'ok';", false);
    }

    /**
     * The live availability check, debounced at the same 400ms the mobile handle
     * field uses — so the two clients feel the same and neither hammers the endpoint.
     */
    public function test_the_handle_is_checked_live_with_suggestions(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee('/handle/available?handle=', false)
            ->assertSee('}, 400);', false)
            ->assertSee('data.suggestions || []', false);
    }

    /** A slow answer for an edited handle must not overwrite what is being typed now. */
    public function test_a_stale_handle_response_is_discarded(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee('if (this.form.handle !== raw) return;', false);
    }

    public function test_the_handle_format_matches_the_api(): void
    {
        // HandleService::FORMAT — 3 to 20 lowercase letters, numbers, underscores.
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee('/^[a-z0-9_]{3,20}$/', false);
    }

    /** Interests are the community-type vocabulary, not a private list. */
    public function test_interests_come_from_the_shared_community_type_vocabulary(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee("window.kb.api('/lookup/community-types'", false);
    }

    public function test_the_join_step_reads_open_communities(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee("'/communities/discover?'", false);
    }

    /**
     * The payload mobile builds omits empty optionals so a re-run never clobbers a
     * value with a blank. Same here.
     */
    public function test_optional_fields_are_omitted_rather_than_sent_empty(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee('if (this.form.city_id) body.city_id = this.form.city_id;', false)
            ->assertSee('if (this.form.photo) body.photo = this.form.photo;', false);
    }

    /** Photo travels as a data-URI, the shape the API and the mobile client share. */
    public function test_the_photo_is_sent_as_a_data_uri(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee('reader.readAsDataURL(file);', false);
    }

    /** Anyone who is not a half-finished attendee has no business here. */
    public function test_only_an_unfinished_attendee_stays_on_the_flow(): void
    {
        $this->page('/onboarding/attendee')
            ->assertOk()
            ->assertSee("if (!this.isAttendee) { window.nav('/dashboard'); return; }", false)
            ->assertSee("if (me.handle) { window.nav('/dashboard'); return; }", false);
    }

    // ── The shell knows about the role ──────────────────────────────────

    public function test_the_shell_recognises_the_attendee_role(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee("get isAttendee() { return this.me?.user_type === 'attendee'; }", false)
            ->assertSee('get needsAttendeeOnboarding() { return this.isAttendee && !this.me?.handle; }', false)
            ->assertSee('role_attendee', false);
    }

    /**
     * Put in loadShell() rather than each page's init(), so a page added later
     * cannot forget it and leave an attendee on a screen addressed to nobody.
     */
    public function test_an_unfinished_attendee_is_bounced_from_every_page(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee('if (this.redirectIfOnboardingIncomplete()) return null;', false)
            // …and the onboarding page itself must not bounce to itself forever.
            ->assertSee("if (here.startsWith('/onboarding')) return false;", false);
    }

    /** An attendee sells nothing and posts nothing (ROLES §7.2). */
    public function test_the_seller_surfaces_are_hidden_from_an_attendee(): void
    {
        $page = $this->page('/dashboard')->assertOk();

        $page->assertSee('x-show="!isAttendee"', false);
        $page->assertSee('x-show="isAttendee"', false);
    }

    public function test_an_attendee_gets_a_wallet_in_the_nav(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee(__('webapp.nav.tickets'));
    }

    /** BE-NF-39's nav entry must survive the role rework — and stay non-attendee. */
    public function test_the_suggestions_entry_is_still_there_for_sellers(): void
    {
        config()->set('suggestions.enabled', true);

        $this->page('/dashboard')
            ->assertOk()
            ->assertSee(__('webapp.nav.suggestions'));
    }

    // ── Attendee home ───────────────────────────────────────────────────

    public function test_the_attendee_home_leads_with_the_next_ticket(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee('x-if="nextTicket"', false)
            ->assertSee(__('webapp.dashboard.next_up'))
            ->assertSee(__('webapp.dashboard.whats_on'));
    }

    /** /me/dashboard is about kolabs and collaborations — meaningless for this role. */
    public function test_the_attendee_home_does_not_ask_for_the_seller_dashboard(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee('if (!this.isAttendee) {', false)
            ->assertSee('if (this.isAttendee) { await this.loadAttendeeExtras(); return; }', false);
    }

    public function test_whats_on_is_scoped_to_the_attendees_own_city(): void
    {
        $this->page('/dashboard')
            ->assertOk()
            ->assertSee("params.set('city_id', cityId)", false)
            ->assertSee("'/events/discover?'", false);
    }

    // ── The wallet ──────────────────────────────────────────────────────

    public function test_the_wallet_lists_tickets_with_their_qr(): void
    {
        $this->page('/tickets')
            ->assertOk()
            ->assertSee("window.kb.api('/me/tickets'", false)
            ->assertSee('x-html="tk.qr_svg"', false)
            ->assertSee(__('webapp.tickets.code_label'));
    }

    /** The email links to /tickets?t=CODE; at a door nobody should have to search. */
    public function test_the_wallet_opens_the_ticket_the_email_pointed_at(): void
    {
        $this->page('/tickets')
            ->assertOk()
            ->assertSee("new URLSearchParams(location.search).get('t')", false)
            ->assertSee('this.open = match ? match.id', false);
    }

    /** Cancelling promotes whoever is next on the waitlist, so it is offered plainly. */
    public function test_the_wallet_lets_someone_give_their_place_back(): void
    {
        $this->page('/tickets')
            ->assertOk()
            ->assertSee("'/events/' + tk.event.id + '/signup', { method: 'DELETE' }", false)
            ->assertSee(__('webapp.tickets.cancel'));
    }

    // ── The door ────────────────────────────────────────────────────────

    public function test_the_admit_page_reads_the_code_from_the_url(): void
    {
        $this->page('/admit/ABCD234XYZ')
            ->assertOk()
            ->assertSee("parts[0] === 'admit' && parts[1]", false)
            ->assertSee("'/tickets/' + encodeURIComponent(clean) + '/admit'", false);
    }

    /**
     * A QR opens in whatever browser the camera hands it to, so the host may not be
     * signed in on that device. `?next=` brings them back and the scan completes.
     */
    public function test_a_host_who_is_not_signed_in_still_completes_the_scan(): void
    {
        $this->page('/admit/ABCD234XYZ')
            ->assertOk()
            ->assertSee('if (!window.kb.requireAuth()) return;', false);
    }

    /** Double-scanning is normal at a busy door; it is its own state, not an error. */
    public function test_an_already_used_ticket_gets_its_own_answer(): void
    {
        $this->page('/admit/ABCD234XYZ')
            ->assertOk()
            ->assertSee("if (res.status === 409) { this.state = 'already'; return; }", false)
            ->assertSee(__('webapp.admit.already'));
    }

    /** The scan tells the doorkeeper who they just let in — not who is signed in. */
    public function test_the_door_names_the_person_admitted(): void
    {
        $this->page('/admit/ABCD234XYZ')
            ->assertOk()
            ->assertSee('data.ticket?.holder_name', false)
            ->assertSee('x-text="holderName"', false);
    }

    /** When the QR will not scan, someone reads the code out. */
    public function test_the_door_falls_back_to_typing_the_code(): void
    {
        $this->page('/admit/ABCD234XYZ')
            ->assertOk()
            ->assertSee(__('webapp.admit.manual_title'))
            ->assertSee('@keydown.enter="admit(typed)"', false);
    }
}
