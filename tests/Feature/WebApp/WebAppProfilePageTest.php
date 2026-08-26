<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

/**
 * The panel's profile page — the full view, as opposed to the public teaser at
 * kolabing.com/p/{slug} (see Tests\Feature\Marketing\PublicProfilePageTest).
 *
 * Shell tests: the page is a public Blade shell that reads /api/v1 client-side, so
 * what is pinned here is the wiring — which endpoints it calls, which sections it
 * renders, and that every surface in the app can reach it.
 */
class WebAppProfilePageTest extends TestCase
{
    private function profilePage(): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')->assertOk();
    }

    private function host(): string
    {
        return config('webapp.host');
    }

    public function test_the_profile_page_renders_every_section(): void
    {
        $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')
            ->assertOk()
            ->assertSee('profilePage()', false)
            ->assertSee('Reviews &amp; feedback', false)
            ->assertSee('Past events')
            ->assertSee('Past collaborations')
            ->assertSee('Photos')
            ->assertSee('Rating breakdown')
            ->assertSee('About');
    }

    public function test_it_reads_the_rich_profile_plus_reviews_and_collaborations(): void
    {
        // Four sources: the role-agnostic rich profile, the reputation block that
        // only /profiles/{id} carries, and the two paginated lists.
        $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')
            ->assertOk()
            ->assertSee("'/public-profile'", false)
            ->assertSee("window.kb.api('/profiles/' + this.id)", false)
            ->assertSee("'/reviews?per_page=10&page='", false)
            ->assertSee("'/collaborations?per_page=10&page='", false);
    }

    public function test_reviews_show_who_wrote_them_unlike_the_public_teaser(): void
    {
        // The teaser keeps reviewers anonymous; inside the app the identity is the
        // whole point, and it links onward to that reviewer's own profile.
        $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')
            ->assertOk()
            ->assertSee('r.reviewer?.display_name', false)
            ->assertSee("window.kbPath('/profiles/' + r.reviewer.id)", false)
            ->assertSee('Verified Kolab review')
            ->assertSee('Would collaborate again');
    }

    public function test_contact_links_are_shown_in_the_app(): void
    {
        $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')
            ->assertOk()
            ->assertSee('instagram.com/', false)
            ->assertSee('tiktok.com/@', false)
            ->assertSee('p.website', false);
    }

    public function test_your_own_profile_offers_editing_and_sharing(): void
    {
        $this->get('http://'.$this->host().'/profiles/01a0-some-uuid')
            ->assertOk()
            ->assertSee('isMe', false)
            ->assertSee('Edit profile')
            ->assertSee('copyPublicLink()', false)
            ->assertSee('Copy public link');
    }

    public function test_the_page_is_reachable_in_every_locale(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/es/profiles/01a0-some-uuid')->assertOk()->assertSee('Reseñas y feedback', false);
        $this->get('http://'.$host.'/ca/profiles/01a0-some-uuid')->assertOk()->assertSee('Ressenyes i feedback', false);
    }

    public function test_profiles_are_reachable_from_every_surface(): void
    {
        $host = $this->host();

        // Explore card titles, and the detail overlay's creator block.
        $this->get('http://'.$host.'/feed')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + cd.profileId)", false)
            ->assertSee("window.kbPath('/profiles/' + dk.creator_profile.id)", false);

        // Application rows and collaboration partners.
        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + partyId(rq))", false)
            ->assertSee("window.kbPath('/profiles/' + collabPartner(cl).id)", false);

        // Whoever triggered a notification.
        $this->get('http://'.$host.'/notifications')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + nt.actor_profile_id)", false);

        // The person you are talking to.
        $this->get('http://'.$host.'/chats')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + counterpart(active).id)", false);

        // And your own, from the sidebar card + the account page.
        $this->get('http://'.$host.'/dashboard')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + me.id)", false);
        $this->get('http://'.$host.'/account')
            ->assertOk()
            ->assertSee("window.kbPath('/profiles/' + me.id)", false);
    }

    public function test_profile_copy_is_translated_in_every_locale(): void
    {
        $en = array_keys(trans('webapp.profile', [], 'en'));

        foreach (['es', 'ca'] as $locale) {
            $translated = trans('webapp.profile', [], $locale);

            $this->assertIsArray($translated, "webapp.profile is missing for {$locale}");
            $this->assertSame($en, array_keys($translated), "webapp.profile keys drifted in {$locale}");

            foreach ($en as $key) {
                // "Kolabs" and "Photos"/"Fotos" style near-cognates aside, every
                // string should have been translated rather than copied.
                if (in_array($key, ['title', 'about'], true)) {
                    continue;
                }

                $this->assertNotSame(
                    trans("webapp.profile.{$key}", [], 'en'),
                    $translated[$key],
                    "webapp.profile.{$key} was left in English for {$locale}"
                );
            }
        }
    }

    public function test_the_panel_profile_route_does_not_leak_onto_the_marketing_host(): void
    {
        $this->get('http://kolabing.com/profiles/01a0-some-uuid')->assertNotFound();
    }

    // ── The identity mask, rendered (BE-FX-22) ───────────────────────────

    /**
     * The server nulls a community's identity for a free business
     * (`CommunityIdentityMask`), so without this the page would simply render an
     * empty heading and a "?" avatar — closed, but looking broken. It gets the same
     * treatment /suggestions already gives a blurred card.
     */
    public function test_a_masked_identity_is_withheld_visibly_rather_than_left_blank(): void
    {
        $page = $this->profilePage();

        $page->assertSee('x-if="p.identity_masked"', false)
            // Withheld, not substituted.
            ->assertSee('bg-primary/60 blur-sm select-none', false)
            ->assertSee('●●●●●●●●●●', false)
            // Hidden from readers, since the visible bar carries no information.
            ->assertSee('aria-hidden="true"', false);
    }

    /** A blur is a sales moment, never a full-screen block (ROLES §2.5). */
    public function test_the_mask_offers_the_plan_instead_of_blocking_the_page(): void
    {
        $this->profilePage()
            ->assertSee('/subscription?reason=profile', false)
            ->assertSee(__('webapp.profile.masked_cta'), false);
    }

    /**
     * The page must never re-derive the rule from the viewer's role — that is how a
     * community viewer ends up masked, because `hasActiveSubscription()` is false for
     * every non-business. It renders the server's answer and nothing else.
     */
    public function test_the_page_reads_the_servers_flag_and_does_not_recompute_it(): void
    {
        $page = $this->profilePage();

        // `has_active_subscription` legitimately exists in the shared layout's shell
        // (that is where `needsPlan` is computed, for action gating). What must not
        // exist is the profile page combining it with the profile being viewed.
        $page->assertDontSee('needsPlan && p.', false)
            ->assertDontSee("p.user_type === 'community' && needsPlan", false)
            // The one source of truth, used verbatim.
            ->assertSee('p.identity_masked', false);
    }
}
