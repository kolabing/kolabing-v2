<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

/**
 * The attendee's half of the panel: registering as one, and RSVPing to an event.
 *
 * Before this, an attendee could not create an account on the web at all — the role
 * picker offered business and community only, so every attendee surface was in an
 * unpublished mobile app.
 */
class WebAppAttendeeRsvpTest extends TestCase
{
    private function host(): string
    {
        return config('webapp.host');
    }

    public function test_someone_can_register_as_an_attendee(): void
    {
        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            // Blade escapes the apostrophe, so match what survives.
            ->assertSee('going to events')
            ->assertSee("pickRole('attendee')", false)
            // Attendees register then onboard: the register endpoint takes only
            // email/password/terms, and the handle lives behind onboarding.
            ->assertSee("'/auth/register/attendee'", false)
            ->assertSee("'/onboarding/attendee'", false)
            ->assertSee('Handle');
    }

    public function test_an_attendee_is_not_asked_for_seller_details(): void
    {
        // No city gate, no categories, no venue question — an attendee is not
        // selling anything, so nothing else is required of them.
        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee("this.role === 'attendee'", false)
            ->assertSee("if (this.role === 'attendee') {", false);
    }

    public function test_the_register_page_can_be_prefilled_as_an_attendee(): void
    {
        $this->get('http://'.$this->host().'/register?type=attendee')
            ->assertOk()
            ->assertSee("q === 'attendee'", false);
    }

    public function test_the_event_page_gives_a_non_host_an_rsvp(): void
    {
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee("t('events.im_going')", false)
            ->assertSee('You are going')
            ->assertSee('You are on the waitlist')
            ->assertSee("'/signup', { method: 'POST' }", false)
            ->assertSee("'/signup', { method: 'DELETE' }", false)
            ->assertSee('Attending is always free.');
    }

    public function test_the_public_page_hands_its_intent_over_with_rsvp(): void
    {
        /*
         * kolabing.com cannot sign anyone up — the bearer token lives in the app
         * host's storage — so it sends people here with ?rsvp=1 and login carries
         * that through ?next=.
         */
        $this->get('http://'.$this->host().'/events/01a0-some-uuid')
            ->assertOk()
            ->assertSee("new URLSearchParams(location.search).get('rsvp') === '1'", false)
            ->assertSee('!this.going && !this.waitlisted', false);
    }

    public function test_rsvp_copy_is_translated_in_every_locale(): void
    {
        $en = array_keys(trans('webapp.events', [], 'en'));

        foreach (['es', 'ca'] as $locale) {
            $translated = trans('webapp.events', [], $locale);

            $this->assertSame($en, array_keys($translated), "webapp.events keys drifted in {$locale}");
        }

        $this->get('http://'.$this->host().'/es/events/01a0-some-uuid')->assertOk()->assertSee('Vas a ir');
        $this->get('http://'.$this->host().'/ca/events/01a0-some-uuid')->assertOk()->assertSee('Hi vas');
    }
}
