<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * What happens to a visitor who arrives from kolabing.com and is not signed in.
 *
 * The public pages cannot act for anyone — the bearer token lives in this host's
 * localStorage and the marketing origin cannot read it — so every cross-host action is
 * a URL carrying an intent: `/events/{id}?rsvp=1`, `/kolabs/{id}?apply=1`. That only
 * works if the login bounce remembers where the visitor was going. It did not:
 * `requireAuth()` sent them to a bare `/login`, they signed in, landed on the
 * dashboard, and the intent that brought them was gone. These tests pin the repair.
 */
class WebAppPublicHandoffTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function host(): string
    {
        return (string) config('webapp.host');
    }

    public function test_the_login_bounce_remembers_where_the_visitor_was_going(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee("'/login?next=' + encodeURIComponent(intended)", false)
            ->assertSee('here + location.search', false);
    }

    /** ?next= is attacker-supplied, so the login page re-checks it is a local path. */
    public function test_the_remembered_destination_is_a_path_and_cannot_become_an_open_redirect(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee("next.startsWith('/') && !next.startsWith('//')", false);
    }

    /** Bouncing /login to /login?next=/login would be a loop. */
    public function test_the_login_page_does_not_bounce_to_itself(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee("here === '/login' ? '/login'", false);
    }

    public function test_the_kolab_page_opens_the_apply_modal_when_the_public_page_asked_for_it(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee("new URLSearchParams(location.search).get('apply') === '1'", false)
            // Opened, not submitted: applying needs dates, and a business may still
            // hit the paywall (ROLES §2.7), so the decision stays with the user.
            ->assertSee('this.openApply();', false);
    }

    public function test_an_already_applied_kolab_does_not_reopen_the_modal(): void
    {
        $this->get('http://'.$this->host().'/kolabs/01a0-some-uuid')
            ->assertOk()
            ->assertSee('!this.appliedIds.includes(this.id)', false);
    }
}
