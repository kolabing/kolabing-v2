<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

/**
 * The web panel's chat surface. These are shell tests: the page is a public Blade
 * shell that talks to /api/v1 client-side (same as every other web-app page), so
 * what is pinned here is the wiring — the routes, the endpoints the page calls,
 * the deep links into it, and the CSP/asset plumbing real-time needs.
 */
class WebAppChatPageTest extends TestCase
{
    private function host(): string
    {
        return config('webapp.host');
    }

    public function test_chats_page_renders_the_two_pane_inbox(): void
    {
        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            ->assertSee('Messages')
            ->assertSee('chatsPage()', false)
            // Thread list, conversation pane, composer.
            ->assertSee('Search conversations')
            ->assertSee('Pick a conversation')
            ->assertSee('Write a message…', false)
            // The inbox + per-thread endpoints it reads.
            ->assertSee("window.kb.api('/chats')", false)
            ->assertSee("'/messages?per_page=50&page='", false);
    }

    public function test_chats_page_is_reachable_in_every_locale(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/es/chats')->assertOk()->assertSee('Mensajes');
        $this->get('http://'.$host.'/ca/chats')->assertOk()->assertSee('Missatges');
    }

    public function test_the_inbox_is_reachable_with_its_own_badge_from_every_page(): void
    {
        // The badge counts unread MESSAGES, which is a different number from the
        // notification badge sitting on the row below it.
        foreach (['/dashboard', '/feed', '/kolabs', '/notifications', '/account'] as $path) {
            $this->get('http://'.$this->host().$path)
                ->assertOk()
                ->assertSee('/chats', false)
                ->assertSee('chatUnread', false)
                ->assertSee("window.kb.api('/chats/unread-count')", false);
        }
    }

    public function test_kolab_chats_send_through_the_application_endpoint(): void
    {
        // This split is load-bearing, not cosmetic: ChatService::threadRecipientIds()
        // returns [] for collaboration threads, so POST /chats/{thread}/messages
        // delivers a Kolab message with NO notification to the other party. Kolab
        // chats must go through the application endpoint; group chats must not.
        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            ->assertSee("'/applications/' + this.active.application_id + '/messages'", false)
            ->assertSee("'/chats/' + this.active.id + '/messages'", false);
    }

    public function test_kolabs_and_notifications_deep_link_into_a_conversation(): void
    {
        $host = $this->host();

        // Accepted request → its chat; active collaboration → the same chat, keyed
        // by the collaboration (the row has no application id).
        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee("'?application=' + rq.id", false)
            ->assertSee("'?collaboration=' + cl.id", false);

        // A new-message notification targets the application, so it must not fall
        // through to the Requests tab.
        $this->get('http://'.$host.'/notifications')
            ->assertOk()
            ->assertSee("nt.type === 'new_message'", false)
            ->assertSee("'/chats?application=' + nt.target_id", false);

        // …and the page resolves all three of those deep links.
        $this->get('http://'.$host.'/chats')
            ->assertOk()
            ->assertSee("params.get('thread')", false)
            ->assertSee("params.get('application')", false)
            ->assertSee("params.get('collaboration')", false);
    }

    public function test_community_channel_management_is_available_on_the_page(): void
    {
        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            // Create / rename / delete a custom channel, and the block list.
            ->assertSee('Create a channel')
            ->assertSee('Rename channel')
            ->assertSee('Delete this channel?')
            ->assertSee('Who can write here')
            ->assertSee("'/communities/' + this.modalCommunity + '/chats'", false)
            ->assertSee("window.kb.api('/chats/' + this.active.id, { method: 'PATCH'", false)
            ->assertSee("window.kb.api('/chats/' + id, { method: 'DELETE' })", false)
            ->assertSee("'/bans'", false)
            // Only owned communities offer management; the API re-checks anyway.
            ->assertSee("window.kb.api('/me/communities')", false)
            // The roster is the odd one out: it pages on `limit` (not per_page) and
            // nests rows under data.members, which kb.rows() cannot find. Reading it
            // the usual way silently renders an empty block list.
            ->assertSee("'/members?limit=100'", false)
            ->assertSee('members.json?.data?.members', false);
    }

    public function test_the_realtime_client_is_self_hosted_and_configured(): void
    {
        config([
            'webapp.realtime' => [
                'key' => 'test-reverb-key',
                'host' => 'ws.kolabing.test',
                'port' => 443,
                'scheme' => 'https',
            ],
        ]);

        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            // Self-hosted, like Alpine and the fonts — no CDN, the CSP forbids it.
            ->assertSee('/webapp-assets/kb-realtime.js', false)
            ->assertSee('test-reverb-key', false)
            ->assertSee('ws.kolabing.test', false)
            ->assertSee("listen('chat.thread.'", false)
            ->assertSee('message.sent', false);

        $this->assertFileExists(public_path('webapp-assets/kb-realtime.js'));
    }

    public function test_the_app_secret_is_never_exposed_to_the_browser(): void
    {
        config(['reverb.apps.apps' => [['app_id' => 'x', 'key' => 'k', 'secret' => 'super-secret-value']]]);

        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            ->assertDontSee('super-secret-value', false);
    }

    public function test_chat_still_works_when_reverb_is_not_deployed(): void
    {
        // REVERB_APP_KEY is unset until the ops work lands (BE-IF-18). The socket is
        // then disabled and the page must fall back to polling rather than go quiet.
        config(['webapp.realtime' => ['key' => null, 'host' => null, 'port' => 443, 'scheme' => 'https']]);

        $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            ->assertSee('startTicker()', false)
            ->assertSee('refreshActive()', false);
    }

    public function test_web_app_csp_allows_the_reverb_websocket(): void
    {
        // CSP treats wss: as its own scheme — `connect-src https:` does not cover
        // it — so without this the socket is blocked on the deployed host.
        $csp = $this->get('http://'.$this->host().'/chats')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('wss:', $csp);

        // The marketing host has no chat and keeps the stricter policy.
        $marketing = $this->get('http://kolabing.com/')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('wss:', $marketing);
    }

    public function test_chat_copy_is_translated_in_every_locale(): void
    {
        $en = array_keys(trans('webapp.chats', [], 'en'));

        foreach (['es', 'ca'] as $locale) {
            $translated = trans('webapp.chats', [], $locale);

            $this->assertIsArray($translated, "webapp.chats is missing for {$locale}");
            $this->assertSame($en, array_keys($translated), "webapp.chats keys drifted in {$locale}");

            foreach ($en as $key) {
                // "Kolab" is the product's own noun — identical in all three locales.
                if ($key === 'kind_kolab') {
                    continue;
                }

                $this->assertNotSame(
                    trans("webapp.chats.{$key}", [], 'en'),
                    $translated[$key],
                    "webapp.chats.{$key} was left in English for {$locale}"
                );
            }
        }

        $this->assertSame('Messages', trans('webapp.nav.messages', [], 'en'));
        $this->assertSame('Mensajes', trans('webapp.nav.messages', [], 'es'));
        $this->assertSame('Missatges', trans('webapp.nav.messages', [], 'ca'));
    }

    public function test_the_chat_route_does_not_leak_onto_the_marketing_host(): void
    {
        $this->get('http://kolabing.com/chats')->assertNotFound();
    }
}
