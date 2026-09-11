<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Multi-Kolab events on the panel.
 *
 * The backend has had twelve endpoints for this since 2026-08-12 and the panel had
 * **no** surface at all — the whole feature was mobile-only. This is the panel's
 * first pass: the list, the organizer's board, a dashboard section and a nav entry.
 *
 * The panel is Blade + Alpine with no build step, so the behaviour under test ships
 * as source inside the page and these assertions quote the shipped expressions. That
 * is not a proxy for the behaviour, it *is* the artifact the browser runs.
 */
class WebAppMultiKolabEventsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    private function list(): TestResponse
    {
        return $this->get('http://'.$this->host().'/multi-kolab-events')->assertOk();
    }

    private function detail(): TestResponse
    {
        return $this->get('http://'.$this->host().'/multi-kolab-events/01a0-some-uuid')->assertOk();
    }

    private function dashboard(): TestResponse
    {
        return $this->get('http://'.$this->host().'/dashboard')->assertOk();
    }

    // ── Routing ─────────────────────────────────────────────────────────

    public function test_both_pages_are_reachable_on_the_app_host(): void
    {
        $this->list();
        $this->detail();
    }

    /**
     * `/events` is the attendee happening — a door with a QR — and a multi-Kolab
     * event is a different object with a different audience. Sharing the path would
     * have made one of them wrong.
     */
    public function test_the_surface_does_not_collide_with_the_attendee_events_page(): void
    {
        $this->get('http://'.$this->host().'/events')
            ->assertOk()
            ->assertSee(__('webapp.events.title'), false)
            ->assertDontSee('multiKolabEventsPage()', false);
    }

    /** The marketing host must not answer for a panel-only surface. */
    public function test_the_marketing_host_does_not_serve_it(): void
    {
        $this->get('http://kolabing.com/multi-kolab-events')->assertNotFound();
    }

    // ── The entitlement gate ────────────────────────────────────────────

    /**
     * Gated on the GRANT, never on `user_type`. `canCreateEvents` reads
     * `has_event_creator_entitlement` from `GET /me/organizer-entitlement` and
     * nothing else — the same shape the Community Hub uses for `can_manage`.
     */
    public function test_the_nav_entry_is_gated_on_the_entitlement_and_not_a_role(): void
    {
        $this->list()
            ->assertSee('canCreateEvents', false)
            ->assertSee('organizerEntitlement?.has_event_creator_entitlement === true', false);
    }

    /**
     * Applying to a role needs no entitlement, so someone without one is told what
     * they *can* do rather than shown an empty list with no way out.
     */
    public function test_a_viewer_without_the_entitlement_is_pointed_at_explore(): void
    {
        $this->list()
            ->assertSee('!loading && !canCreateEvents && events.length === 0', false)
            ->assertSee(__('webapp.mke.no_entitlement_body'), false);
    }

    /** An attendee cannot hold the capability, so the panel does not ask for it. */
    public function test_the_entitlement_is_not_fetched_for_an_attendee(): void
    {
        $this->list()->assertSee('if (this.isAttendee) return;', false);
    }

    // ── The list ────────────────────────────────────────────────────────

    public function test_the_list_reads_only_the_viewers_own_events(): void
    {
        $this->list()->assertSee("window.kb.api('/multi-kolab-events/me?per_page=15')", false);
    }

    /** "3 of 5 roles filled" — the numerator alone says nothing. */
    public function test_a_row_states_roles_filled_as_a_fraction(): void
    {
        $this->list()
            ->assertSee("window.t('mke.roles_filled', { filled, total })", false);
    }

    // ── The organizer's board ───────────────────────────────────────────

    public function test_the_detail_page_reads_the_event_and_the_board_together(): void
    {
        $this->detail()
            ->assertSee("window.kb.api('/multi-kolab-events/' + this.id)", false)
            ->assertSee("window.kb.api('/multi-kolab-events/' + this.id + '/dashboard')", false);
    }

    /**
     * The board 403s for anyone who is not the organizer, and that is not an error
     * worth showing — the page still has the event, and the roles fall back to the
     * detail payload without the application counts.
     */
    public function test_a_forbidden_board_is_not_treated_as_a_failure(): void
    {
        $this->detail()
            ->assertSee('this.board = board.ok ? (board.json?.data || null) : null;', false)
            // Only the event's own failure stops the page.
            ->assertSee('if (!detail.ok) {', false);
    }

    /** Four zeroes in a row answer nothing; the line exists to show a waiting decision. */
    public function test_only_application_stages_with_applications_are_printed(): void
    {
        $this->detail()
            ->assertSee('.filter((stage) => (counts[stage] ?? 0) > 0)', false)
            ->assertSee(__('webapp.mke.no_applications'), false);
    }

    /** One status-pill helper for the whole panel, not a second one for this feature. */
    public function test_statuses_reuse_the_panels_own_pill_helper(): void
    {
        $this->detail()->assertSee('window.kbStatus(r.status)', false);
        $this->list()->assertSee('return window.kbStatus(status);', false);
    }

    // ── The dashboard section ───────────────────────────────────────────

    public function test_the_dashboard_shows_the_newest_events(): void
    {
        $this->dashboard()
            ->assertSee("window.kb.api('/multi-kolab-events/me?per_page=3')", false)
            ->assertSee('mkeEvents', false);
    }

    /**
     * Shown to whoever has events, and to an entitled organizer with none yet so the
     * surface is reachable — otherwise a brand-new organizer has no path in.
     */
    public function test_the_dashboard_section_appears_for_events_or_for_the_grant(): void
    {
        $this->dashboard()
            ->assertSee('mkeEvents.length > 0 || canCreateEvents', false)
            ->assertSee(__('webapp.mke.title'), false);
    }

    /**
     * The events are fetched unconditionally rather than behind `canCreateEvents`:
     * the entitlement call is still in flight while `loadExtras()` runs, so gating on
     * it would race and sometimes show an organizer nothing.
     */
    public function test_the_dashboard_does_not_race_the_entitlement_call(): void
    {
        $this->dashboard()->assertDontSee("canCreateEvents ? window.kb.api('/multi-kolab-events", false);
    }
}
