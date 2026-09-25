<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Jobs\SendTransactionalEmail;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * ROLES §2.5: a free business must not see a community's name, logo or contact.
 *
 * `PublicProfileMaskTest` pins the profile endpoints. This file pins the other
 * door: the "new application" notification (in-app row, push and email), the
 * notification list's actor fields, and the application the notification links
 * to. Each leak is tested both ways — masked for a free business, unmasked for a
 * subscribed one — plus the guard-order case: a community recipient must never
 * be masked, because `hasActiveSubscription()` is false for every non-business.
 */
class NotificationIdentityMaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(bool $subscribed = false, string $locale = 'es'): Profile
    {
        $profile = Profile::factory()->business()->create([
            'preferred_locale' => $locale,
            'is_test_user' => false,
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id, 'name' => 'Joe Cafe']);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);
        }

        return $profile->fresh();
    }

    private function community(string $name = 'Barcelona Run Club', string $locale = 'es'): Profile
    {
        $profile = Profile::factory()->community()->create([
            'avatar_url' => 'https://cdn.test/community.png',
            'preferred_locale' => $locale,
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'instagram' => '@barcelonarunclub',
        ]);

        return $profile->fresh();
    }

    private function applicationTo(Profile $creator, Profile $applicant): Application
    {
        $kolab = Kolab::factory()->published()->create([
            'creator_profile_id' => $creator->id,
            'title' => 'Summer Popup',
        ]);

        return Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type,
        ]);
    }

    private function onlyNotificationFor(Profile $recipient): Notification
    {
        return Notification::query()->where('profile_id', $recipient->id)->sole();
    }

    // ── Application received: in-app, push, email ────────────────────────

    public function test_a_free_business_is_told_a_community_applied_without_its_name(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false, locale: 'es');
        $community = $this->community();

        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($business, $community));

        $row = $this->onlyNotificationFor($business);
        $this->assertSame('Una comunidad se ha postulado a tu oportunidad "Summer Popup".', $row->body);
        $this->assertStringNotContainsString('Barcelona Run Club', $row->title.$row->body);

        Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->recipient->id === $business->id
            && ! str_contains($job->title.$job->body, 'Barcelona Run Club')
            && str_contains($job->body, 'Una comunidad'));

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['alias'] === 'application-received'
            && $job->data['model']['applicant_name'] === 'Una comunidad');
        Bus::assertNotDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => str_contains(json_encode($job->data['model']), 'Barcelona Run Club'));
    }

    public function test_the_neutral_name_follows_the_recipients_locale(): void
    {
        Bus::fake();
        $en = $this->business(subscribed: false, locale: 'en');
        $ca = $this->business(subscribed: false, locale: 'ca');

        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($en, $this->community()));
        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($ca, $this->community('Gracia Yoga')));

        $this->assertStringStartsWith('A community ', $this->onlyNotificationFor($en)->body);
        $this->assertStringStartsWith('Una comunitat ', $this->onlyNotificationFor($ca)->body);
    }

    public function test_a_subscribed_business_sees_who_applied(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: true);
        $community = $this->community();

        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($business, $community));

        $this->assertStringContainsString('Barcelona Run Club', $this->onlyNotificationFor($business)->body);
        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['alias'] === 'application-received'
            && $job->data['model']['applicant_name'] === 'Barcelona Run Club');
    }

    /** Guard order: the subscription test alone would mask every community. */
    public function test_a_community_recipient_is_never_masked(): void
    {
        Bus::fake();
        $owner = $this->community('Kolab Owner Club');
        $applicant = $this->community('Barcelona Run Club');

        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($owner, $applicant));

        $this->assertStringContainsString('Barcelona Run Club', $this->onlyNotificationFor($owner)->body);
    }

    public function test_a_business_applicant_is_named_to_a_free_business(): void
    {
        Bus::fake();
        $owner = $this->business(subscribed: false);
        $applicant = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $applicant->id, 'name' => 'Eixample 46']);

        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($owner, $applicant->fresh()));

        // There is no business-identity paywall.
        $this->assertStringContainsString('Eixample 46', $this->onlyNotificationFor($owner)->body);
    }

    public function test_the_real_apply_endpoint_does_not_leak_the_name(): void
    {
        $business = $this->business(subscribed: false, locale: 'en');
        $community = $this->community();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/applications", [
                'message' => 'We would love to collaborate with your venue this summer.',
                'availability' => 'Available on weekends and evenings throughout the month.',
            ])
            ->assertStatus(201);

        $row = $this->onlyNotificationFor($business);
        $this->assertSame(NotificationType::ApplicationReceived, $row->type);
        $this->assertStringStartsWith('A community ', $row->body);
        $this->assertStringNotContainsString('Barcelona Run Club', $row->body);
    }

    // ── Application withdrawn ────────────────────────────────────────────

    public function test_a_free_business_is_told_a_community_withdrew_without_its_name(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();

        app(NotificationService::class)->notifyApplicationWithdrawn($this->applicationTo($business, $community));

        $row = $this->onlyNotificationFor($business);
        $this->assertStringContainsString('Una comunidad', $row->body);
        $this->assertStringNotContainsString('Barcelona Run Club', $row->body);

        // The community's own confirmation is unaffected.
        $this->assertStringNotContainsString('Una comunidad', $this->onlyNotificationFor($community)->body);
    }

    // ── Collaboration notifications to a lapsed business ─────────────────

    public function test_a_lapsed_business_is_not_given_the_community_name_on_a_collaboration_update(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
        ]);

        app(NotificationService::class)->notifyCollaborationCancelled($collaboration, $community);

        $row = $this->onlyNotificationFor($business);
        $this->assertStringNotContainsString('Barcelona Run Club', $row->body);
        $this->assertStringContainsString('Una comunidad', $row->body);
    }

    public function test_a_lapsed_business_gets_the_neutral_partner_name_in_the_feedback_email(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
        ]);

        app(NotificationService::class)->notifyCollabFollowUpReminder($collaboration);

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->to === $business->email
            && $job->data['alias'] === 'feedback-request'
            && $job->data['model']['partner_name'] === 'Una comunidad');
        // The community still gets the business's real name.
        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->to === $community->email
            && $job->data['model']['partner_name'] === 'Joe Cafe');
    }

    public function test_a_subscribed_business_is_given_the_community_name_on_a_collaboration_update(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: true);
        $community = $this->community();
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
        ]);

        app(NotificationService::class)->notifyCollaborationCancelled($collaboration, $community);

        $this->assertStringContainsString('Barcelona Run Club', $this->onlyNotificationFor($business)->body);
    }

    // ── GET /notifications actor fields ──────────────────────────────────

    public function test_the_notification_list_withholds_the_actor_from_a_free_business(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();
        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($business, $community));

        $item = $this->actingAs($business)->getJson('/api/v1/me/notifications')->assertOk()->json('data.0');

        $this->assertTrue($item['actor_identity_masked']);
        $this->assertNull($item['actor_profile_id']);
        $this->assertNull($item['actor_name']);
        $this->assertNull($item['actor_avatar_url']);
        $this->assertStringNotContainsString('Barcelona Run Club', json_encode($item));
    }

    public function test_subscribing_reveals_the_actor_on_existing_notifications(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();
        app(NotificationService::class)->notifyApplicationReceived($this->applicationTo($business, $community));

        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $item = $this->actingAs($business->fresh())->getJson('/api/v1/me/notifications')->assertOk()->json('data.0');

        $this->assertFalse($item['actor_identity_masked']);
        $this->assertSame($community->id, $item['actor_profile_id']);
        $this->assertSame('Barcelona Run Club', $item['actor_name']);
        $this->assertSame('https://cdn.test/community.png', $item['actor_avatar_url']);
    }

    public function test_a_community_sees_the_actor_on_its_notifications(): void
    {
        Bus::fake();
        $business = $this->business(subscribed: false);
        $community = $this->community();
        app(NotificationService::class)->notifyApplicationAccepted($this->applicationTo($business, $community));

        $item = $this->actingAs($community)->getJson('/api/v1/me/notifications')->assertOk()->json('data.0');

        $this->assertFalse($item['actor_identity_masked']);
        $this->assertSame($business->id, $item['actor_profile_id']);
        $this->assertSame('Joe Cafe', $item['actor_name']);
    }

    // ── The application the notification links to ───────────────────────

    public function test_the_linked_application_withholds_the_applicant_from_a_free_business(): void
    {
        $business = $this->business(subscribed: false);
        $community = $this->community();
        $application = $this->applicationTo($business, $community);

        $applicant = $this->actingAs($business)->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()->json('data.applicant_profile');

        $this->assertTrue($applicant['identity_masked']);
        $this->assertNull($applicant['display_name']);
        $this->assertNull($applicant['avatar_url']);
        $this->assertSame([], $applicant['public_channels']);
        $this->assertSame([], $applicant['portfolio_photos']);
        $this->assertStringNotContainsString('barcelonarunclub', strtolower(json_encode($applicant)));
    }

    public function test_the_linked_application_shows_the_applicant_to_a_subscribed_business(): void
    {
        $business = $this->business(subscribed: true);
        $community = $this->community();
        $application = $this->applicationTo($business, $community);

        $applicant = $this->actingAs($business)->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()->json('data.applicant_profile');

        $this->assertFalse($applicant['identity_masked']);
        $this->assertSame('Barcelona Run Club', $applicant['display_name']);
        $this->assertSame('https://cdn.test/community.png', $applicant['avatar_url']);
    }

    public function test_the_applicant_community_sees_its_own_application_unmasked(): void
    {
        $business = $this->business(subscribed: false);
        $community = $this->community();
        $application = $this->applicationTo($business, $community);

        $applicant = $this->actingAs($community)->getJson("/api/v1/applications/{$application->id}")
            ->assertOk()->json('data.applicant_profile');

        $this->assertFalse($applicant['identity_masked']);
        $this->assertSame('Barcelona Run Club', $applicant['display_name']);
    }
}
