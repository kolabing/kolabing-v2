<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

/**
 * The phone-frame preview beside every Profile tab, pinned against the Flutter
 * screen it mirrors (kolabing-app public_profile_screen.dart and friends).
 *
 * Shell tests, like the rest of the webapp suite: the partial is Blade + Alpine that
 * reads /api/v1 client-side, so what is worth locking is the wiring and the mirrored
 * measurements — the endpoints it calls, the sections it renders in the app's order,
 * and the specific numbers/colours that were wrong before and would silently go wrong
 * again. Rendering fidelity itself is a human check against a device.
 */
class WebAppPhonePreviewParityTest extends TestCase
{
    /** Every Profile tab renders the same partial. */
    private const TABS = ['', '/gallery', '/events', '/preview'];

    private function host(): string
    {
        return config('webapp.host');
    }

    private function tab(string $path = '/gallery'): \Illuminate\Testing\TestResponse
    {
        return $this->get('http://'.$this->host().'/account'.$path)->assertOk();
    }

    public function test_it_reads_the_same_three_endpoints_the_app_reads(): void
    {
        // The Flutter screen makes three calls; a single call to
        // /profiles/{id}/public-profile was the cause of most of the drift, because
        // CommunityPublicProfileResource carries no reputation and no reviews.
        $this->tab()
            ->assertSee("window.kb.api('/profiles/' + id)", false)
            ->assertSee("'/collaborations?per_page=10'", false)
            ->assertSee("'/events?profile_id=' + encodeURIComponent(id) + '&limit=10'", false);
    }

    public function test_the_preview_no_longer_reads_the_endpoint_without_a_reputation_block(): void
    {
        $this->tab()
            ->assertDontSee("+ '/public-profile')", false);
    }

    public function test_the_header_shows_the_formatted_type_label_not_the_raw_slug(): void
    {
        // `type` is the slug (run_club); `type_label` is what the app renders.
        $this->tab()
            ->assertSee('previewTypeLabel', false)
            ->assertSee('line-clamp-2', false);
    }

    public function test_the_reputation_card_has_both_of_the_apps_states(): void
    {
        // ReputationSummaryCard swaps to an EmptyStateCard when there are no reviews;
        // the old preview always drew the rating row, so it read "— · 0 reviews".
        $this->tab()
            ->assertSee('previewHasReviews', false)
            ->assertSee('No reviews yet')
            ->assertSee('Completed Kolabs will appear here once partners leave reviews.');
    }

    public function test_the_reputation_stats_hide_at_zero_and_include_partners(): void
    {
        // "0 partners" is noise on a fresh profile, so the app hides both counts at
        // zero — and it shows unique_partner_count, which the preview used to drop.
        $this->tab()
            ->assertSee("previewCount('partners', previewPartnerCount)", false)
            ->assertSee('previewPartnerCount > 0', false)
            ->assertSee('previewCompletedCount > 0', false);
    }

    public function test_the_recent_reviews_card_exists(): void
    {
        // Between past Kolabs and social links in the app; absent from the preview
        // entirely until now, because its endpoint never returned recent_reviews.
        $this->tab()
            ->assertSee('previewReviews', false)
            ->assertSee('Recent Reviews')
            ->assertSee('View more')
            ->assertSee('previewReviewDate(review.created_at)', false)
            ->assertSee('star <= Number(review.rating || 0)', false);
    }

    public function test_past_kolabs_is_always_rendered_with_a_count_and_an_empty_state(): void
    {
        // _buildCollaborationsSection is unconditional, titled "Past Kolabs", counted
        // by completed_kolabs_count, and branches on the LIST so a count without rows
        // shows the empty state instead of a blank box.
        $this->tab()
            ->assertSee('Past Kolabs')
            ->assertSee('No past kolabs yet')
            ->assertSee('previewKolabsCount', false)
            ->assertSee('!previewCollaborations.length', false);
    }

    public function test_the_kolab_cards_mirror_the_dart_measurements(): void
    {
        $this->tab()
            ->assertSee('w-[240px]', false)
            ->assertSee('h-[110px]', false)
            ->assertSee('previewCollabMonth(collab.completed_at)', false)
            ->assertSee("'with ' + (collab.partner_name || '')", false)
            ->assertSee('bg-[#56624D]/[.15]', false)
            ->assertSee('Completed');
    }

    public function test_the_event_cards_are_full_bleed_covers_not_white_cards(): void
    {
        // EventCard is a 180x220 cover with the text over a three-stop gradient, a
        // date badge and a photo-count badge — not a 120px image above white text.
        $this->tab()
            ->assertSee('w-[180px]', false)
            ->assertSee('h-[220px]', false)
            ->assertSee('linear-gradient(to bottom, transparent 30%, rgba(0,0,0,.3) 60%, rgba(0,0,0,.8) 100%)', false)
            ->assertSee('previewDateBadge(event.date)', false)
            ->assertSee('previewEventPhotoCount(event) > 1', false)
            ->assertSee("previewAttendeeCount(event) + ' attendees'", false);
    }

    public function test_it_uses_the_apps_palette_and_not_the_panels_theme_tokens(): void
    {
        // The panel's `white`/`ink` tokens are theme-aware; the replica must keep
        // showing the app's light theme whatever theme the panel is in.
        $this->tab()
            ->assertSee('background:#FAF5EA', false)
            ->assertSee('#EDE5D5', false)
            ->assertSee('#3F3A32', false)
            ->assertSee('#8C8A82', false)
            ->assertSee('#FFF4C2', false);
    }

    public function test_section_titles_use_the_apps_type_scale(): void
    {
        // titleMedium is Inter 20/700 lh 28. The preview used to render 14px bold.
        $this->tab()
            ->assertSee('text-[20px] font-bold leading-[28px]', false);
    }

    public function test_every_tab_renders_the_rewritten_preview(): void
    {
        foreach (self::TABS as $path) {
            $this->tab($path)
                ->assertSee('kbPhonePreview()', false)
                ->assertSee('Past Kolabs')
                ->assertSee('Recent Reviews');
        }
    }

    public function test_the_mirrored_copy_is_the_apps_own_copy_per_locale(): void
    {
        // Lifted from kolabing-app/lib/l10n/app_*.arb so the replica reads like the
        // screen. `Gallery` stays English everywhere because the Dart hardcodes it.
        $this->get('http://'.$this->host().'/es/account/gallery')
            ->assertOk()
            ->assertSee('Kolabs anteriores')
            ->assertSee('Reseñas recientes')
            ->assertSee('Aún no hay reseñas')
            ->assertSee('Gallery');

        $this->get('http://'.$this->host().'/ca/account/gallery')
            ->assertOk()
            ->assertSee('Kolabs anteriors')
            ->assertSee('Ressenyes recents')
            ->assertSee('Encara no hi ha ressenyes')
            ->assertSee('Gallery');
    }

    public function test_every_phone_preview_key_exists_in_every_locale(): void
    {
        $keys = [
            'title', 'about', 'gallery', 'past_events', 'past_kolabs', 'no_past_kolabs',
            'recent_reviews', 'view_more', 'links', 'reviews', 'reviews_one', 'partners',
            'partners_one', 'completed', 'completed_one', 'reputation_empty_title',
            'reputation_empty_body', 'error', 'note',
        ];

        foreach (['en', 'es', 'ca'] as $locale) {
            $block = __('webapp.account.phone', [], $locale);

            $this->assertIsArray($block, "webapp.account.phone missing for [$locale]");

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $block, "webapp.account.phone.$key missing for [$locale]");
                $this->assertNotSame('', trim((string) $block[$key]), "webapp.account.phone.$key empty for [$locale]");
            }
        }
    }

    public function test_the_plural_helper_covers_every_counted_string(): void
    {
        // window.t() has no plural support, so each counted app string needs a
        // singular twin. previewCount() picks between them.
        foreach (['reviews', 'partners', 'completed'] as $key) {
            $this->assertSame(
                '1 '.rtrim($key, 's'),
                __("webapp.account.phone.{$key}_one", [], 'en'),
                "the singular form of [$key] drifted"
            );
        }
    }
}
