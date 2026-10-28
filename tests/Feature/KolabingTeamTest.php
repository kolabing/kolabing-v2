<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ChatThreadType;
use App\Enums\KolabingTeamOfferKind;
use App\Enums\KolabingTeamOfferStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Jobs\SendTransactionalEmail;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\KolabingTeamOffer;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\KolabingTeamService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Kolabing speaks as itself: an offer card in a "Kolabing team" thread,
 * one approved row = one send (SERR-658, spec agreed on SERR-656).
 */
class KolabingTeamTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function city(): City
    {
        return City::query()->where('name', 'Barcelona')->first()
            ?? City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain', 'is_active' => true]);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function business(array $profile = []): Profile
    {
        $business = Profile::factory()->business()->create([
            'preferred_locale' => 'en',
            'last_active_at' => now()->subDay(),
            ...$profile,
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Cafe Mallorca',
            'city_id' => $this->city()->id,
            'city_name' => 'Barcelona',
        ]);

        return $business;
    }

    private function community(array $profile = []): Profile
    {
        $community = Profile::factory()->community()->create([
            'preferred_locale' => 'en',
            'last_active_at' => now()->subDay(),
            ...$profile,
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Sunrise Run Club',
            'city_id' => $this->city()->id,
            'community_type' => 'run_club',
            'community_size' => 120,
        ]);

        return $community;
    }

    private function openKolab(?Profile $owner = null): Kolab
    {
        return Kolab::factory()->venuePromotion()->published()->create([
            'creator_profile_id' => ($owner ?? $this->business())->id,
            'title' => 'Host your run club at Cafe Mallorca',
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
        ]);
    }

    private function service(): KolabingTeamService
    {
        return app(KolabingTeamService::class);
    }

    private function communityOffer(Profile $community, ?Kolab $kolab = null, array $extra = []): KolabingTeamOffer
    {
        return $this->service()->draft([
            'kind' => KolabingTeamOfferKind::CommunityApply->value,
            'recipient' => $community->id,
            'kolab' => ($kolab ?? $this->openKolab())->id,
            'message' => 'We think your club fits this venue.',
            ...$extra,
        ], $this->maintainer());
    }

    public function test_a_sent_offer_lands_in_the_team_thread_as_kolabing_not_as_a_profile(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $offer = $this->communityOffer($community);
        $profilesBefore = Profile::query()->count();

        $this->assertSame(KolabingTeamOfferStatus::Draft, $offer->status);
        $this->assertSame(0, ChatMessage::query()->count(), 'a draft reaches nobody');

        $this->service()->send($offer, $admin);

        $message = ChatMessage::query()->sole();
        $this->assertNull($message->sender_profile_id);
        $this->assertTrue($message->isFromTeam());
        $this->assertSame($admin->id, $message->sender_user_id);
        $this->assertSame($offer->id, $message->team_offer_id);

        $thread = ChatThread::query()->sole();
        $this->assertSame(ChatThreadType::KolabingTeam, $thread->type);
        $this->assertSame($community->id, $thread->team_profile_id);
        $this->assertSame($profilesBefore, Profile::query()->count(), 'no Kolabing profile is created');

        $fresh = $offer->fresh();
        $this->assertSame(KolabingTeamOfferStatus::Sent, $fresh->status);
        $this->assertSame($admin->id, $fresh->sent_by_admin_id);
        $this->assertNotNull($fresh->sent_at);

        $notification = Notification::query()->where('profile_id', $community->id)->sole();
        $this->assertSame(NotificationType::KolabingTeamMessage, $notification->type);
        $this->assertSame($thread->id, $notification->target_id);
        Queue::assertPushed(SendPushNotification::class);
    }

    public function test_the_recipient_sees_the_thread_in_the_inbox_with_the_offer_card_and_an_unread_badge(): void
    {
        $community = $this->community();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());
        $thread = ChatThread::query()->sole();

        $inbox = $this->actingAs($community)->getJson('/api/v1/chats')->assertOk();
        $row = collect($inbox->json('data'))->firstWhere('id', $thread->id);
        $this->assertNotNull($row, 'team thread is in the Chats inbox');
        $this->assertSame('kolabing_team', $row['type']);
        $this->assertSame('Kolabing team', $row['name']);
        $this->assertSame(1, $row['unread_count']);

        $this->actingAs($community)->getJson('/api/v1/chats/unread-count')->assertOk()->assertJsonPath('data.total', 1);

        $messages = $this->actingAs($community)->getJson("/api/v1/chats/{$thread->id}/messages")->assertOk();
        $m = $messages->json('data.messages.0');
        $this->assertSame('team', $m['sender_type']);
        $this->assertSame('Kolabing team', $m['sender_name']);
        $this->assertNull($m['sender_profile']);
        $this->assertFalse($m['is_own']);
        $this->assertSame($offer->id, $m['offer']['id']);
        $this->assertSame('community_apply', $m['offer']['kind']);
        $this->assertTrue($m['offer']['can_respond']);

        $this->actingAs($community)->getJson('/api/v1/chats/unread-count')->assertJsonPath('data.total', 0);
    }

    public function test_nobody_else_can_read_or_write_the_team_thread(): void
    {
        $community = $this->community();
        $this->service()->send($this->communityOffer($community), $this->maintainer());
        $thread = ChatThread::query()->sole();

        $other = $this->community();
        $this->actingAs($other)->getJson("/api/v1/chats/{$thread->id}/messages")->assertForbidden();
        $this->actingAs($other)->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'hi'])->assertForbidden();
    }

    public function test_a_community_accepting_applies_to_the_kolab_once(): void
    {
        $community = $this->community();
        $kolab = $this->openKolab();
        $offer = $this->communityOffer($community, $kolab);
        $this->service()->send($offer, $this->maintainer());

        $this->actingAs($community)->postJson("/api/v1/kolabing-team/offers/{$offer->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'accepted');

        $application = Application::query()->where('kolab_id', $kolab->id)->sole();
        $this->assertSame($community->id, $application->applicant_profile_id);
        $this->assertSame($application->id, $offer->fresh()->result_application_id);

        $this->actingAs($community)->postJson("/api/v1/kolabing-team/offers/{$offer->id}/accept")->assertStatus(422);
        $this->assertSame(1, Application::query()->where('kolab_id', $kolab->id)->count());
    }

    public function test_not_now_is_final_and_a_stranger_cannot_answer(): void
    {
        $community = $this->community();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());

        $this->actingAs($this->community())->postJson("/api/v1/kolabing-team/offers/{$offer->id}/decline")->assertForbidden();
        $this->assertSame(KolabingTeamOfferStatus::Sent, $offer->fresh()->status);

        $this->actingAs($community)->postJson("/api/v1/kolabing-team/offers/{$offer->id}/decline")
            ->assertOk()->assertJsonPath('data.offer.status', 'declined');
        $this->actingAs($community)->postJson("/api/v1/kolabing-team/offers/{$offer->id}/accept")->assertStatus(422);
        $this->assertSame(0, Application::query()->count());
    }

    public function test_a_business_without_a_subscription_accepting_creates_the_direct_proposal(): void
    {
        $business = $this->business();
        $community = $this->community();
        $this->assertFalse($business->hasActiveSubscription());

        $offer = $this->service()->draft([
            'kind' => KolabingTeamOfferKind::BusinessPropose->value,
            'recipient' => $business->id,
            'community' => $community->id,
            'title' => 'Host a run club night',
            'proposed_date' => now()->addDays(10)->toDateString(),
        ], $this->maintainer());
        $this->service()->send($offer, $this->maintainer());

        $card = $this->actingAs($business)
            ->getJson('/api/v1/chats/'.ChatThread::query()->sole()->id.'/messages')
            ->assertOk()->json('data.messages.0.offer');
        $this->assertSame('A local community', $card['community_label'], 'no community name without a subscription');

        $this->actingAs($business)->postJson("/api/v1/kolabing-team/offers/{$offer->id}/accept")
            ->assertOk()->assertJsonPath('data.offer.status', 'accepted');

        $kolab = Kolab::query()->where('creator_profile_id', $business->id)->sole();
        $this->assertSame($community->id, $kolab->recipient_community_id);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertSame($kolab->id, $offer->fresh()->result_kolab_id);
        $this->assertTrue(Notification::query()->where('profile_id', $community->id)->where('type', NotificationType::KolabInvite->value)->exists());
    }

    public function test_the_subscription_bypass_does_not_open_the_public_direct_proposal_path(): void
    {
        $business = $this->business();
        $community = $this->community();
        $kolab = Kolab::factory()->venuePromotion()->create(['creator_profile_id' => $business->id, 'preferred_city' => 'Barcelona']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('active subscription');
        app(\App\Services\KolabService::class)->publish($kolab, ['recipient_community_id' => $community->id]);
    }

    public function test_a_subscribed_business_sees_the_community_name_on_the_card(): void
    {
        $business = $this->business();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        $community = $this->community();

        $offer = $this->service()->draft([
            'kind' => 'business_propose', 'recipient' => $business->id, 'community' => $community->id, 'title' => 'Run club night',
        ], $this->maintainer());
        $this->service()->send($offer, $this->maintainer());

        $card = $this->actingAs($business)
            ->getJson('/api/v1/chats/'.ChatThread::query()->sole()->id.'/messages')->json('data.messages.0.offer');
        $this->assertSame('Sunrise Run Club', $card['community_label']);
    }

    public function test_one_row_is_one_send_and_a_resend_is_refused(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $offer = $this->communityOffer($community);

        $this->service()->send($offer, $admin);

        try {
            $this->service()->send($offer->fresh(), $admin);
            $this->fail('A sent row must not send again.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already sent', $e->getMessage());
        }

        $this->assertSame(1, ChatMessage::query()->count());

        $this->expectException(InvalidArgumentException::class);
        $this->communityOffer($community, $offer->kolab);
    }

    public function test_real_sends_skip_test_deleted_switched_off_and_blocked_accounts(): void
    {
        $admin = $this->maintainer();
        $kolab = $this->openKolab();

        $test = $this->community(['is_test_user' => true]);
        $off = $this->community(['is_active' => false]);
        $gone = $this->community();
        $gone->delete();
        $blocked = $this->community();
        UserBlock::query()->create(['blocker_profile_id' => $kolab->creator_profile_id, 'blocked_profile_id' => $blocked->id]);

        foreach ([$test, $off, $gone, $blocked] as $profile) {
            $offer = $this->service()->draft([
                'kind' => 'community_apply', 'recipient' => $profile->id, 'kolab' => $kolab->id,
            ], $admin);
            $this->assertNotSame([], $this->service()->problems($offer->fresh()), $profile->id.' must be refused');

            try {
                $this->service()->send($offer, $admin);
                $this->fail('Send must be refused for '.$profile->id);
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_weekly_cap_limits_offers_per_recipient(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();

        for ($i = 0; $i < KolabingTeamService::DEFAULT_WEEKLY_CAP; $i++) {
            $this->service()->send($this->communityOffer($community), $admin);
        }

        $third = $this->communityOffer($community);
        $this->assertStringContainsString('cap', implode(' ', $this->service()->problems($third)));
        $this->expectException(InvalidArgumentException::class);
        $this->service()->send($third, $admin);
    }

    public function test_a_test_send_reaches_a_test_account_and_only_a_test_account(): void
    {
        $admin = $this->maintainer();
        $testAccount = $this->community(['is_test_user' => true]);
        $real = $this->community();

        $offer = $this->communityOffer($testAccount, null, ['test_send' => true]);
        $this->service()->send($offer, $admin);

        $this->assertTrue($offer->fresh()->is_test_send);
        $this->assertSame(KolabingTeamOfferStatus::Sent, $offer->fresh()->status);
        $this->assertSame(1, ChatMessage::query()->where('team_offer_id', $offer->id)->count());
        $this->assertSame(1, Notification::query()->where('profile_id', $testAccount->id)->count());

        $wrong = $this->communityOffer($real, null, ['test_send' => true]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('test accounts');
        $this->service()->send($wrong, $admin);
    }

    public function test_push_follows_the_switch_but_the_in_app_message_is_always_written(): void
    {
        $community = $this->community();
        NotificationPreference::query()->updateOrCreate(['profile_id' => $community->id], ['collaboration_updates' => false]);

        $this->service()->send($this->communityOffer($community), $this->maintainer());

        $this->assertSame(1, ChatMessage::query()->count());
        $this->assertSame(1, Notification::query()->where('profile_id', $community->id)->count());
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_an_account_that_never_opened_the_app_also_gets_the_email_and_a_whatsapp_link(): void
    {
        $community = $this->community(['last_active_at' => null, 'device_token' => null, 'phone_number' => '+34 600 111 222', 'email' => 'new@club.test']);
        $offer = $this->communityOffer($community);

        $this->service()->send($offer, $this->maintainer());

        $this->assertSame('email', $offer->fresh()->channel);
        $this->assertSame(1, ChatMessage::query()->count(), 'the offer is waiting in the thread for when they sign in');
        Queue::assertPushed(SendTransactionalEmail::class);
        $this->assertStringStartsWith('https://wa.me/34600111222?text=', (string) $this->service()->whatsappLink($offer->fresh()));
    }

    public function test_replies_land_in_admin_and_the_team_can_answer(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $this->service()->send($this->communityOffer($community), $admin);
        $thread = ChatThread::query()->sole();

        $this->actingAs($community)->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Can we move it to Friday?'])
            ->assertCreated();
        $reply = ChatMessage::query()->where('sender_profile_id', $community->id)->sole();
        $this->assertSame($thread->id, $reply->thread_id);

        $this->actingAs($admin, 'admin')->get(route('admin.kolabing-team.inbox'))
            ->assertOk()->assertSee('Sunrise Run Club');
        $this->actingAs($admin, 'admin')->get(route('admin.kolabing-team.thread', $thread))
            ->assertOk()->assertSee('Can we move it to Friday?');

        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.reply', $thread), ['content' => 'Friday works.'])
            ->assertRedirect(route('admin.kolabing-team.thread', $thread));

        $teamReply = ChatMessage::query()->where('thread_id', $thread->id)->latest('created_at')->first();
        $this->assertTrue($teamReply->isFromTeam());
        $this->assertSame('Friday works.', $teamReply->content);
        $this->assertSame(2, $this->actingAs($community)->getJson('/api/v1/chats')->json('data.0.unread_count'), 'the unread offer and the new answer');
    }

    public function test_admin_sends_one_row_at_a_time_and_only_maintainers_can(): void
    {
        $community = $this->community();
        $kolab = $this->openKolab();
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.store'), [
            'kind' => 'community_apply', 'recipient' => $community->email, 'kolab' => $kolab->id, 'message' => 'Hello',
        ])->assertRedirect(route('admin.kolabing-team.index'));

        $offer = KolabingTeamOffer::query()->sole();
        $this->assertSame(KolabingTeamOfferStatus::Draft, $offer->status);
        $this->assertSame(0, ChatMessage::query()->count());

        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.kolabing-team.send', $offer))->assertForbidden();
        $this->assertSame(0, ChatMessage::query()->count());

        $this->actingAs($admin, 'admin')->get(route('admin.kolabing-team.index'))->assertOk()->assertSee('Send');
        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.send', $offer))
            ->assertRedirect(route('admin.kolabing-team.index'));
        $this->assertSame(1, ChatMessage::query()->count());

        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.send', $offer))->assertSessionHasErrors('send');
        $this->assertSame(1, ChatMessage::query()->count());
    }

    public function test_reach_report_counts_real_accounts_that_opened_the_app(): void
    {
        $this->business(['last_active_at' => now()->subDays(2)]);
        $this->business(['last_active_at' => null, 'device_token' => null]);
        $this->business(['last_active_at' => now(), 'is_test_user' => true]);
        $this->community(['last_active_at' => null, 'device_token' => 'tok']);

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('kolabing:team-reach', ['--json' => true]));
        $out = \Illuminate\Support\Facades\Artisan::output();
        $rows = collect(json_decode($out, true))->keyBy('type');
        $this->assertSame(2, $rows['business']['real_accounts'], 'test users are not counted');
        $this->assertSame(1, $rows['business']['opened_the_app']);
        $this->assertSame(50.0, (float) $rows['business']['opened_pct']);
        $this->assertSame(1, $rows['community']['opened_the_app']);
    }
}
