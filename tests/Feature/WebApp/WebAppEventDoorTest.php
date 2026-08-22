<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The panel's event surface — the piece that was missing entirely. The check-in API
 * shipped with thirteen endpoints and no client: events, sign-ups, QR generation and
 * check-in were all mobile-only, and neither mobile app is published. So the data
 * engine existed with nothing able to turn it.
 *
 * Shell tests: these pages read /api/v1 client-side, so what is pinned is the
 * wiring — the routes, the endpoints they call, and the paths through login.
 */
class WebAppEventDoorTest extends TestCase
{
    // The public feed assertion below queries events; the rest are shell renders.
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return config('webapp.host');
    }

    public function test_the_events_page_lists_turnout_and_offers_a_new_event(): void
    {
        $this->get('http://'.$this->host().'/events')
            ->assertOk()
            ->assertSee('eventsPage()', false)
            ->assertSee('Signed up')
            ->assertSee('Showed up')
            ->assertSee('Turnout')
            ->assertSee('New event')
            // The list endpoint nests under data.events with its own pagination.
            ->assertSee('res.json?.data?.events', false)
            ->assertSee("window.kb.api('/events', { method: 'POST'", false);
    }

    public function test_the_event_page_is_the_door(): void
    {
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee('eventDoorPage()', false)
            ->assertSee('Open check-in')
            ->assertSee('or type this code')
            ->assertSee('Who came')
            // The QR arrives as inline SVG, because a bearer token cannot ride on <img src>.
            ->assertSee('x-html="door.qr_svg"', false)
            ->assertSee("'/generate-qr', {", false)
            ->assertSee("'/checkins?per_page=100'", false);
    }

    public function test_the_door_refreshes_itself_while_it_is_open(): void
    {
        // An organiser stands at an entrance; the count has to move without being asked.
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee('setInterval', false)
            ->assertSee('document.hidden', false);
    }

    public function test_the_checkin_page_is_one_screen_with_one_outcome(): void
    {
        $this->get('http://'.$this->host().'/checkin/ABCD1234')
            ->assertOk()
            ->assertSee('checkinPage()', false)
            ->assertSee('You are in')
            ->assertSee('You are already checked in')
            // The typed fallback: a camera that will not focus must not end the attempt.
            ->assertSee('Enter the code')
            ->assertSee("window.kb.api('/checkin', { method: 'POST'", false)
            // 409 is the friendly case, not an error.
            ->assertSee('res.status === 409', false);
    }

    public function test_an_unauthenticated_scan_is_sent_to_log_in_and_brought_back(): void
    {
        // Someone at a door will not find their way back on their own.
        $this->get('http://'.$this->host().'/checkin/ABCD1234')
            ->assertOk()
            ->assertSee("'/login?next=' + encodeURIComponent('/checkin/' + this.token)", false);

        // And login honours it, for both the password and the Google path.
        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('kbPostAuthTarget', false);
    }

    public function test_the_post_auth_target_refuses_anything_but_a_local_path(): void
    {
        // ?next= is attacker-supplied; an absolute URL here would be an open redirect.
        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee("next.startsWith('/') && !next.startsWith('//')", false);
    }

    public function test_events_are_reachable_from_the_sidebar_in_every_locale(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/dashboard')->assertOk()->assertSee('/events', false)->assertSee('Events');
        $this->get('http://'.$host.'/es/events')->assertOk()->assertSee('Eventos');
        $this->get('http://'.$host.'/ca/events')->assertOk()->assertSee('Esdeveniments');
        $this->get('http://'.$host.'/es/checkin/ABCD1234')->assertOk()->assertSee('Estás dentro');
    }

    public function test_event_copy_is_translated_in_every_locale(): void
    {
        foreach (['events', 'checkin'] as $group) {
            $en = array_keys(trans("webapp.{$group}", [], 'en'));

            foreach (['es', 'ca'] as $locale) {
                $translated = trans("webapp.{$group}", [], $locale);

                $this->assertIsArray($translated, "webapp.{$group} is missing for {$locale}");
                $this->assertSame($en, array_keys($translated), "webapp.{$group} keys drifted in {$locale}");
            }
        }
    }

    public function test_the_door_screen_stays_in_step_with_the_other_client(): void
    {
        // Web and mobile watch the same door. Arrivals arrive over the socket so both
        // move at once; the poll stays as the fallback and slows down while it is live.
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee('/webapp-assets/kb-realtime.js', false)
            ->assertSee("rt.listen('event.' + this.id + '.door', 'checkin.recorded'", false)
            ->assertSee('this.live && this.tick++ % 5 !== 0', false);
    }

    public function test_reopening_the_door_does_not_silently_kill_another_screens_code(): void
    {
        // Opening is idempotent; retiring a code is a separate, confirmed action.
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee("method: 'POST', body: { rotate }", false)
            ->assertSee('window.confirm(t(\'events.new_code_hint\'))', false)
            ->assertSee('retires the current code everywhere');
    }

    public function test_the_two_hosts_serve_different_things_at_the_same_path(): void
    {
        /*
         * `/events` now exists on both hosts and that is deliberate: the marketing
         * host serves the public "what's on" feed, the app host serves the
         * organiser's list. What must not leak is the door — check-in belongs to the
         * panel, where a session exists.
         */
        $this->get('http://kolabing.com/events')->assertOk()->assertSee('on near you');
        $this->get('http://kolabing.com/checkin/ABCD1234')->assertNotFound();

        // And the marketing event page resolves public events only, so a panel-style
        // id does not become a way in.
        $this->get('http://kolabing.com/events/01a0-some-uuid')->assertNotFound();
    }
}
