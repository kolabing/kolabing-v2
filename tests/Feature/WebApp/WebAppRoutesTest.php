<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class WebAppRoutesTest extends TestCase
{
    private function host(): string
    {
        return config('webapp.host');
    }

    /** The locale path prefix for the default locale (empty). */
    private function base(): string
    {
        return '';
    }

    public function test_login_is_an_overlay_over_the_hero_not_a_standalone_page(): void
    {
        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('Log in')
            ->assertSee('Welcome back')
            // The sheet, and the hero it sits on top of, are both rendered.
            ->assertSee('kbLoginModal', false)
            ->assertSee('Where businesses &amp; communities grow together', false);
    }

    public function test_the_hero_opens_the_same_login_overlay(): void
    {
        // Arriving from the marketing site never needs a separate login page load.
        $this->get('http://'.$this->host().'/')
            ->assertOk()
            ->assertSee('kbLoginModal', false)
            ->assertSee('openLogin()', false)
            // Sign-up stays a full page — it is a multi-step flow.
            ->assertSee($this->base().'/register', false);
    }

    public function test_register_page_renders_the_role_picker_and_the_account_step(): void
    {
        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee('Get started')
            ->assertSee("I'm a community")
            ->assertSee('Create your account');
    }

    public function test_subscription_page_renders_on_the_app_host(): void
    {
        // Public shell; auth + data are enforced client-side against /api/v1.
        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('Your plan')
            ->assertSee('KOLABING BUSINESS')
            ->assertSee('noindex', false);
    }

    public function test_subscription_page_shows_both_plans_priced_from_config(): void
    {
        config([
            'subscriptions.business.stripe.monthly.price' => 49,
            'subscriptions.business.stripe.three_months.price' => 129,
        ]);

        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('Monthly')
            ->assertSee('3 months')
            ->assertSee('€49')
            // €129 / 3 = €43 a month, 12% under the monthly plan — both derived.
            ->assertSee('€43')
            ->assertSee('Save 12%')
            ->assertSee('€129 billed every 3 months');
    }

    public function test_checkout_returns_to_the_confirmation_page_not_a_static_thanks(): void
    {
        // The success URL must carry Stripe's session-id placeholder, which is what
        // lets the return page activate the plan without waiting on the webhook.
        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('/subscription/success?session_id={CHECKOUT_SESSION_ID}', false);

        $this->get('http://'.$this->host().'/subscription/success')
            ->assertOk()
            ->assertSee('Confirming your payment')
            ->assertSee('/me/subscription/checkout/confirm', false);

        $this->get('http://'.$this->host().'/es/subscription/success')
            ->assertOk()
            ->assertSee('Confirmando tu pago');
        $this->get('http://'.$this->host().'/ca/subscription/success')->assertOk();
    }

    public function test_paywalled_actions_send_the_reason_to_the_plan_page(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee('/subscription?reason=publish', false)
            ->assertSee('/subscription?reason=accept', false);

        $this->get('http://'.$host.'/kolabs/create')
            ->assertOk()
            ->assertSee('/subscription?reason=publish', false);

        // …and the plan page has copy for each of them.
        $this->get('http://'.$host.'/subscription')
            ->assertOk()
            ->assertSee('Publishing a Kolab needs an active plan', false);
    }

    public function test_every_authed_page_carries_the_failed_payment_alert(): void
    {
        // A declined card silently removes access; the shell must say so and offer
        // the fix. The banner ships with the sidebar, so it cannot be forgotten on
        // a new page.
        foreach (['/dashboard', '/feed', '/kolabs', '/subscription', '/account'] as $path) {
            $this->get('http://'.$this->host().$path)
                ->assertOk()
                ->assertSee('We could not charge your card')
                ->assertSee('Update payment method')
                ->assertSee('pastDue', false);
        }
    }

    public function test_app_root_and_welcome_render(): void
    {
        $this->get('http://'.$this->host().'/')
            ->assertOk()
            ->assertSee('Where businesses &amp; communities grow together', false);

        $this->get('http://'.$this->host().'/welcome')
            ->assertOk()
            ->assertSee('Continue in the Kolabing app');
    }

    public function test_explore_my_kolabs_and_notifications_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/feed')->assertOk()->assertSee('Explore');
        $this->get('http://'.$host.'/kolabs')->assertOk()->assertSee('My Kolabs');
        $this->get('http://'.$host.'/notifications')->assertOk()->assertSee('Notifications');
    }

    public function test_kolab_create_and_detail_pages_render(): void
    {
        $host = $this->host();
        // "create" must resolve to the wizard, not be captured by the {kolab} route.
        $this->get('http://'.$host.'/kolabs/create')->assertOk()->assertSee('Create a Kolab');
        $this->get('http://'.$host.'/kolabs/some-uuid')->assertOk();
        $this->get('http://'.$host.'/kolabs/some-uuid/edit')->assertOk();
    }

    public function test_account_and_applications_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/account')->assertOk()->assertSee('Profile');
        // /applications reuses the My Kolabs view, opened on the Requests tab.
        // /applications reuses the My Kolabs view — assert it actually boots on the
        // Requests tab, not merely that the word appears somewhere on the page.
        $this->get('http://'.$host.'/applications')
            ->assertOk()
            ->assertSee("myKolabsPage('requests')", false)
            ->assertSee('<title>Applications | Kolabing</title>', false);

        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee("myKolabsPage('offers')", false);
    }

    public function test_suggestions_page_renders_the_card_frame_under_every_locale(): void
    {
        config(['suggestions.enabled' => true]);
        $host = $this->host();

        $this->get('http://'.$host.'/suggestions')
            ->assertOk()
            ->assertSee('suggestionsPage(', false)
            ->assertSee('Suggested partners')
            // The two actions, and where each one goes.
            ->assertSee('Create this Kolab')
            ->assertSee('/kolabs/create?suggestion=', false)
            ->assertSee('Not interested')
            ->assertSee("/suggestions/' + card.id + '/dismiss", false)
            // The blur is a sales moment with a route out of it…
            ->assertSee('Community hidden on the free plan')
            ->assertSee('/subscription?reason=suggestion', false)
            // …and it is whatever SuggestionResource decided, never re-derived from
            // the viewer's role — a community is never blurred (ROLES §3.6).
            ->assertSee('const blurred = !!s.is_identity_blurred;', false)
            ->assertSee('blur-sm select-none', false)
            // Every figure on the card carries the basis it came from — the engine
            // never invents a number and the card has to show that. These are source
            // assertions, not behaviour: a route-render test cannot execute Alpine,
            // so they pin the two rules the captions depend on rather than prove them.
            ->assertSee("basisCaption('attendance', fmt.attendance_basis, fmt.expected_attendance)", false)
            ->assertSee("basisCaption('weekday', fmt.weekday_basis, this.weekdayLabel(fmt.weekday))", false)
            // tOr, so an unrecognised basis renders no caption instead of a raw key…
            ->assertSee("return window.tOr('suggestions.basis.' + field + '_' + slug, '');", false)
            // …and a caption with no figure to qualify renders neither.
            ->assertSee("if (!qualifies || !slug) return '';", false)
            // The empty state names the fix instead of apologising.
            ->assertSee('No suggestions yet')
            ->assertSee('Complete your profile')
            ->assertSee('/account', false);

        $this->get('http://'.$host.'/es/suggestions')
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Socios sugeridos')
            ->assertSee('Crear este Kolab')
            ->assertSee('Comunidad oculta en el plan gratuito')
            // In-app links keep the locale prefix.
            ->assertSee('/es/kolabs/create?suggestion=', false)
            ->assertSee('/es/subscription?reason=suggestion', false);

        $this->get('http://'.$host.'/ca/suggestions')
            ->assertOk()
            ->assertSee('lang="ca"', false)
            ->assertSee('Socis suggerits')
            ->assertSee('Crea aquest Kolab');
    }

    public function test_the_suggestions_page_and_its_nav_entry_are_gated_by_the_feature_flag(): void
    {
        $host = $this->host();

        // Flag off (the shipped default): the page 404s like the API it reads, and
        // the shell must not advertise a route that does not answer.
        config(['suggestions.enabled' => false]);
        $this->get('http://'.$host.'/suggestions')->assertNotFound();
        $this->get('http://'.$host.'/es/suggestions')->assertNotFound();
        $this->get('http://'.$host.'/ca/suggestions')->assertNotFound();

        // The nav label itself is no proof either way: the layout inlines the whole
        // `webapp` dictionary into window.KB_I18N on every page, so "Suggestions" is
        // in the HTML regardless. The link is what the flag has to remove.
        $this->get('http://'.$host.'/dashboard')
            ->assertOk()
            ->assertDontSee('href="/suggestions"', false);

        config(['suggestions.enabled' => true]);
        $this->get('http://'.$host.'/dashboard')
            ->assertOk()
            ->assertSee('href="/suggestions"', false)
            ->assertSee('Suggestions');

        $this->get('http://'.$host.'/es/dashboard')
            ->assertOk()
            ->assertSee('href="/es/suggestions"', false);
    }

    /**
     * The pre-filled create form (BE-NF-28).
     *
     * A route-render test cannot execute Alpine, so these are **source**
     * assertions: they prove each rule is written, and written the one way that
     * keeps it safe — not that it runs. What actually runs is covered where it
     * can be: `suggestion_id` by CreateKolabRequest's own tests, and the payload
     * shape this maps from by SuggestionApiTest.
     */
    public function test_the_create_form_prefills_from_a_suggestion_and_carries_the_id_to_the_post(): void
    {
        config(['suggestions.enabled' => true]);
        $host = $this->host();

        $this->get('http://'.$host.'/kolabs/create')
            ->assertOk()
            // The flag is resolved server-side, so the form never chases a prefill
            // the API would 404.
            ->assertSee('const suggestionsEnabled = true;', false)
            // ?suggestion={id} → GET /suggestions/{id}
            ->assertSee("new URLSearchParams(location.search).get('suggestion')", false)
            ->assertSee("window.kb.api('/suggestions/' + encodeURIComponent(this.suggestionId))", false)
            // A broken suggestion is silent and never blocks creation: no error is
            // set on the failure path, and the id is dropped with it so a bad link
            // cannot turn into a 422 on `exists` at submit.
            ->assertSee("if (!res.ok) { this.suggestionId = ''; return; }", false)
            // What closes the funnel: the id survives every edit, to the POST.
            ->assertSee('if (!this.isEdit && this.suggestionId) body.suggestion_id = this.suggestionId;', false)
            // Mapped field by field. The weekday goes in as the ISO 1..7 that
            // `recurring_days` stores — no shifting, since the two conventions
            // differ only on Sunday and a shift would be wrong once a week…
            ->assertSee('Number.isInteger(weekday) && weekday >= 1 && weekday <= 7', false)
            ->assertSee('this.form.recurring_days = [weekday];', false)
            // …a time only in the H:i shape `selected_time` validates…
            ->assertSee('/^([01]\d|2[0-3]):[0-5]\d$/.test(', false)
            // …a title clamped to what the column takes…
            ->assertSee('String(fmt.title).slice(0, 255)', false)
            // …attendance only where it means the same thing (a community's turnout,
            // never a business `capacity`, which is a fact about a venue)…
            ->assertSee('this.form.typical_attendance = attendance;', false)
            // …and each chip list in the viewer's own vocabulary, filtered against
            // the options actually on screen, so anything pre-filled is also
            // something the user can un-pick.
            ->assertSee("this.form.offers_in_return = this.knownOptions('deliverables', fmt.offer);", false)
            ->assertSee("this.form.needs = this.knownOptions('needs', fmt.expects);", false)
            ->assertSee("this.form.offering = this.knownOptions('offerings', fmt.offer);", false)
            ->assertSee('return (Array.isArray(values) ? values : []).filter(v => known.has(v));', false)
            // Built from `suggested_format` alone: a blurred card (a free business —
            // name, avatar and counterpart id all null) pre-fills identically.
            ->assertSee('res.json?.data?.suggested_format || {}', false)
            // Only an intent this account may actually post.
            ->assertSee("const allowed = this.isCommunity ? ['community'] : ['venue', 'product'];", false)
            // And the banner says the one thing a prefill has to say — bound to the
            // prefill having landed, never to the URL parameter.
            ->assertSee('x-if="suggestionApplied"', false)
            ->assertSee('Pre-filled from a suggested partner. Change anything');

        $this->get('http://'.$host.'/es/kolabs/create')
            ->assertOk()
            ->assertSee('Rellenado desde un socio sugerido. Cambia lo que quieras');

        $this->get('http://'.$host.'/ca/kolabs/create')
            ->assertOk()
            ->assertSee('soci suggerit. Canvia el que vulguis');
    }

    public function test_creating_a_kolab_never_depends_on_the_suggestion_flag(): void
    {
        config(['suggestions.enabled' => false]);
        $host = $this->host();

        // Kolab creation is not a suggestions feature. With the flag off the form
        // renders exactly as before and simply stops asking for a prefill.
        $this->get('http://'.$host.'/kolabs/create')
            ->assertOk()
            ->assertSee('Create a Kolab')
            ->assertSee('const suggestionsEnabled = false;', false);

        $this->get('http://'.$host.'/es/kolabs/create')->assertOk()->assertSee('Crear un Kolab');
    }

    public function test_the_plan_page_explains_a_blurred_suggestion_as_the_existing_gate(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/subscription')
            ->assertOk()
            // The reason the suggestions card already sends is on the allowlist. It
            // was not, so the banner rendered nothing at all.
            ->assertSee("['publish', 'accept', 'apply', 'create', 'welcome', 'suggestion'].includes(reason)", false)
            // The copy names what was held back, and names the two actions ROLES
            // §2.7 gates rather than inventing a third paywall.
            ->assertSee('The community name and logo behind a suggestion stay hidden on the free plan', false)
            ->assertSee('accepting an application and applying to a Kolab', false);

        $this->get('http://'.$host.'/es/subscription')
            ->assertOk()
            ->assertSee('aceptar una solicitud y aplicar a un Kolab', false);

        $this->get('http://'.$host.'/ca/subscription')
            ->assertOk()
            ->assertSee('i aplicar a un Kolab', false);
    }

    public function test_every_allowlisted_paywall_reason_has_copy_in_every_locale(): void
    {
        // The banner reads `t('subscription.reason.' + reason)`, and t() falls back
        // to the key — so a reason allowlisted without copy prints the raw dotted
        // path onto the plan page. The allowlist is read out of the view itself so
        // this keeps holding for reasons added after this test was written.
        $view = (string) file_get_contents(resource_path('views/webapp/subscription.blade.php'));

        $this->assertSame(1, preg_match('/\[([^\]]*)\]\.includes\(reason\)/', $view, $matches));
        $this->assertGreaterThan(0, preg_match_all("/'([a-z_]+)'/", $matches[1], $found));

        $reasons = $found[1];
        $this->assertContains('suggestion', $reasons);

        foreach ($reasons as $reason) {
            foreach (['en', 'es', 'ca'] as $locale) {
                $key = 'webapp.subscription.reason.'.$reason;

                // `fallback: false` — an English sentence shown to a Catalan reader
                // is a missing translation, not a pass.
                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "?reason={$reason} is allowlisted but has no [{$locale}] copy."
                );
            }
        }
    }

    public function test_the_dashboard_suggestion_block_shows_the_top_card_or_nothing(): void
    {
        $host = $this->host();
        config(['suggestions.enabled' => true]);

        $this->get('http://'.$host.'/dashboard')
            ->assertOk()
            ->assertSee("suggestionsEnabled ? window.kb.api('/suggestions?per_page=1') : Promise.resolve(null)", false)
            // An empty list shows no block at all: the wrapper is bound to the top
            // card, which stays null, rather than to a count that would read "0".
            ->assertSee('this.suggestionTop = rows.length ? this.suggestionCard(rows[0]) : null;', false)
            ->assertSee('x-show="!loadingExtras && suggestionTop"', false)
            // The count is the paginator's total, not the one row fetched for the card.
            ->assertSee('this.suggestionCount = window.kb.meta(sugg).total || rows.length;', false)
            ->assertSee('suggestions this week')
            ->assertSee('suggestion this week')
            // The blur is whatever SuggestionResource decided — never re-derived
            // from the viewer's role, because a community is never blurred.
            ->assertSee('const blurred = !!s.is_identity_blurred;', false)
            ->assertSee('href="/suggestions"', false);

        // Flag off: no block, and no request either.
        config(['suggestions.enabled' => false]);
        $this->get('http://'.$host.'/dashboard')
            ->assertOk()
            ->assertSee('const suggestionsEnabled = false;', false)
            ->assertDontSee('x-show="!loadingExtras && suggestionTop"', false);
    }

    public function test_the_web_app_dictionary_is_complete_and_consistent_in_every_locale(): void
    {
        // The web app is at 100% es/ca and stays there: a page shipped with only
        // English copy would render a raw dotted key to a Spanish reader.
        $en = $this->flattenTranslations((array) trans('webapp', [], 'en'));
        $this->assertArrayHasKey('suggestions.title', $en);

        // Every basis FormatSuggester can persist for a figure the card shows…
        foreach (['attendance_past_events', 'attendance_community_size', 'weekday_series', 'weekday_past_events'] as $basis) {
            $this->assertArrayHasKey('suggestions.basis.'.$basis, $en);
        }

        // …and the two "no basis" slugs deliberately have none: that absence is what
        // makes them render no caption at all rather than one that says nothing.
        $this->assertArrayNotHasKey('suggestions.basis.attendance_profile_only', $en);
        $this->assertArrayNotHasKey('suggestions.basis.weekday_none', $en);

        foreach (['es', 'ca'] as $locale) {
            $translated = $this->flattenTranslations((array) trans('webapp', [], $locale));

            $this->assertSame(
                [],
                array_values(array_diff(array_keys($en), array_keys($translated))),
                "lang/{$locale}/webapp.php is missing keys that lang/en/webapp.php has."
            );
            $this->assertSame(
                [],
                array_values(array_diff(array_keys($translated), array_keys($en))),
                "lang/{$locale}/webapp.php has keys lang/en/webapp.php does not."
            );

            foreach ($en as $key => $english) {
                // A translation that drops or renames a :placeholder ships a sentence
                // with a hole in it, or a leaked ":count", to that locale only.
                $this->assertSame(
                    $this->placeholders($english),
                    $this->placeholders($translated[$key]),
                    "lang/{$locale}/webapp.php [{$key}] does not carry the same :params as English."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private function flattenTranslations(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flattenTranslations($value, $dotted);

                continue;
            }

            $flat[$dotted] = (string) $value;
        }

        return $flat;
    }

    /**
     * @return array<int, string>
     */
    private function placeholders(string $line): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $line, $matches);

        $found = array_unique($matches[1]);
        sort($found);

        return array_values($found);
    }

    public function test_localized_public_pages_render_with_lang_and_hreflang(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/es/login')
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Iniciar sesión')
            ->assertSee('hreflang="ca"', false)
            ->assertSee('hreflang="x-default"', false);

        $this->get('http://'.$host.'/ca/login')
            ->assertOk()
            ->assertSee('lang="ca"', false)
            ->assertSee('Inicia la sessió');

        // The default (en) is served at the root.
        $this->get('http://'.$host.'/login')->assertOk()->assertSee('lang="en"', false)->assertSee('Log in');

        // "create" still resolves under a locale prefix (not swallowed by {kolab}).
        $this->get('http://'.$host.'/es/kolabs/create')->assertOk()->assertSee('Crear un Kolab');

        // Deep authed pages localise too (server-rendered copy).
        $this->get('http://'.$host.'/es/feed')->assertOk()->assertSee('Explorar');
        $this->get('http://'.$host.'/ca/account')->assertOk()->assertSee('Perfil');
        $this->get('http://'.$host.'/es/notifications')->assertOk()->assertSee('Notificaciones');

        // An unsupported locale is not a valid prefix.
        $this->get('http://'.$host.'/de/login')->assertNotFound();
    }

    public function test_login_and_register_always_offer_google_sign_in(): void
    {
        // The option must never silently vanish when GOOGLE_CLIENT_ID_WEB is unset —
        // it degrades to a visible, explained state instead.
        config(['services.google.client_id_web' => null]);

        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('Google sign-in is not configured yet', false)
            ->assertSee('googleBtn', false);

        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee('Continue with Google');

        // With a client id configured the live widget mounts instead.
        config(['services.google.client_id_web' => 'test-web-client-id.apps.googleusercontent.com']);

        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('test-web-client-id.apps.googleusercontent.com', false);
    }

    public function test_web_app_host_csp_allows_alpine_and_google_sign_in(): void
    {
        // Alpine compiles x-* expressions with new Function(); without 'unsafe-eval'
        // every component silently fails to initialise and the app is inert.
        $csp = $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://accounts.google.com', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_marketing_host_keeps_the_strict_csp(): void
    {
        // Only the web app needs the eval relaxation — nothing else uses Alpine.
        $csp = $this->get('http://kolabing.com/')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString('accounts.google.com', $csp);
    }

    public function test_web_app_routes_do_not_leak_onto_the_marketing_host(): void
    {
        // /login only exists on the app host — the marketing domain must not expose it.
        $this->get('http://kolabing.com/login')->assertNotFound();
        $this->get('http://kolabing.com/notifications')->assertNotFound();
    }
}
