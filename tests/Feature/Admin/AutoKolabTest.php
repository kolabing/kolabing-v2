<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\ChatThread;
use App\Models\City;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Auto-kolabs (Daniel 2026-10-02): a maintainer sets up a Kolab that is already
 * matched, so it shows as a confirmed collaboration for both sides.
 */
class AutoKolabTest extends TestCase
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

    private function business(bool $subscribed = true, array $profileOverrides = []): Profile
    {
        $city = $this->city();
        $profile = Profile::factory()->business()->create([
            'email' => 'owner@eixample46.test',
            'preferred_locale' => 'en',
            ...$profileOverrides,
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Eixample 46',
            'about' => 'A neighbourhood cafe with a back room.',
            'offering' => 'Back room, free coffee for the group.',
            'city_id' => $city->id,
            'city_name' => 'Barcelona',
            'city_country' => 'Spain',
            'primary_venue' => null,
        ]);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);
        }

        return $profile->fresh();
    }

    private function community(array $profileOverrides = []): Profile
    {
        $profile = Profile::factory()->community()->create([
            'email' => 'hello@runclub.test',
            'preferred_locale' => 'en',
            ...$profileOverrides,
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Real Run Club',
            'city_id' => $this->city()->id,
        ]);

        return $profile->fresh();
    }

    /**
     * The match notifications (mission/badge side effects, if any are seeded,
     * are not this feature's business).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Notification>
     */
    private function matchNotifications(): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::query()->where('type', NotificationType::CollaborationCreated->value)->get();
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    // ── Command ─────────────────────────────────────────────────────────

    public function test_command_is_a_dry_run_by_default(): void
    {
        $business = $this->business();
        $community = $this->community();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->email,
            'community' => $community->email,
        ])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, Kolab::query()->count());
        $this->assertSame(0, Collaboration::query()->count());
    }

    public function test_apply_creates_a_matched_kolab_both_sides_see_as_confirmed(): void
    {
        $business = $this->business();
        $community = $this->community();
        $date = now()->addDays(10)->toDateString();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->email,
            'community' => 'Real Run Club',
            '--date' => $date,
            '--apply' => true,
        ])->assertSuccessful();

        $kolab = Kolab::query()->sole();
        $this->assertTrue($kolab->is_auto_matched);
        $this->assertFalse($kolab->is_auto_listing);
        $this->assertNull($kolab->created_by_admin_id);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertSame($business->id, $kolab->creator_profile_id);
        $this->assertSame($community->id, $kolab->recipient_community_id, 'Direct proposal: never in anyone else\'s Explore.');
        $this->assertSame('Partner with Eixample 46', $kolab->title, 'Default title never names the community (ROLES §2.5).');
        $this->assertSame($date, $kolab->availability_start?->toDateString());

        $application = Application::query()->sole();
        $this->assertSame($community->id, $application->applicant_profile_id);
        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        $this->assertNotNull($application->accepted_at);

        $collaboration = Collaboration::query()->sole();
        $this->assertSame(CollaborationStatus::Scheduled, $collaboration->status);
        $this->assertSame($application->id, $collaboration->application_id);
        $this->assertSame($business->id, $collaboration->creator_profile_id);
        $this->assertSame($community->id, $collaboration->applicant_profile_id);
        $this->assertSame($business->businessProfile->id, $collaboration->business_profile_id);
        $this->assertSame($community->communityProfile->id, $collaboration->community_profile_id);
        $this->assertSame($date, $collaboration->scheduled_date?->toDateString());

        $this->assertTrue(ChatThread::query()->where('application_id', $application->id)->exists());

        // One "Kolabing set it up" notification each, routed to the collaboration;
        // none of the "X applied" / "X accepted you" copy that would be false here.
        $this->assertSame(0, Notification::query()->whereIn('type', [
            NotificationType::ApplicationReceived->value,
            NotificationType::ApplicationAccepted->value,
        ])->count());
        $notifications = $this->matchNotifications();
        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing([$business->id, $community->id], $notifications->pluck('profile_id')->all());
        foreach ($notifications as $notification) {
            $this->assertSame(NotificationType::CollaborationCreated, $notification->type);
            $this->assertSame('collaboration', $notification->target_type);
            $this->assertSame($collaboration->id, $notification->target_id);
            $this->assertSame('Kolabing set up a Kolab for you', $notification->title);
        }
        $this->assertStringContainsString('Real Run Club', $notifications->firstWhere('profile_id', $business->id)->body);
        $this->assertStringContainsString('Eixample 46', $notifications->firstWhere('profile_id', $community->id)->body);

        Queue::assertPushed(
            SendPushNotification::class,
            fn (SendPushNotification $job): bool => $job->type === NotificationType::CollaborationCreated && $job->targetId === $collaboration->id,
        );
        $this->assertSame(2, Queue::pushed(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->type === NotificationType::CollaborationCreated)->count());
    }

    public function test_a_free_business_does_not_learn_the_community_name_from_the_notification(): void
    {
        $business = $this->business(subscribed: false);
        $community = $this->community();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->id,
            'community' => $community->id,
            '--apply' => true,
        ])->assertSuccessful();

        $body = $this->matchNotifications()->where('profile_id', $business->id)->sole()->body;
        $this->assertStringNotContainsString('Real Run Club', $body);
        $this->assertStringContainsString('A community', $body);
    }

    public function test_a_title_naming_the_community_is_refused_for_a_free_business(): void
    {
        $business = $this->business(subscribed: false);
        $community = $this->community();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->id,
            'community' => $community->id,
            '--title' => 'Eixample 46 x Real Run Club',
            '--apply' => true,
        ])->expectsOutputToContain('names the community')->assertFailed();

        $this->assertSame(0, Kolab::query()->count());
    }

    public function test_silent_creates_the_match_without_notifying(): void
    {
        $this->artisan('kolabing:auto-kolab', [
            'business' => $this->business()->email,
            'community' => $this->community()->email,
            '--silent' => true,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Collaboration::query()->count());
        $this->assertCount(0, $this->matchNotifications());
        Queue::assertNotPushed(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->type === NotificationType::CollaborationCreated);
    }

    public function test_the_gift_kolab_does_not_use_up_the_business_free_kolab(): void
    {
        $business = $this->business(subscribed: false);

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->email,
            'community' => $this->community()->email,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertFalse($business->fresh()->hasUsedFreeKolab());
    }

    public function test_a_second_open_auto_kolab_for_the_same_pair_is_refused(): void
    {
        $business = $this->business();
        $community = $this->community();
        $args = ['business' => $business->email, 'community' => $community->email, '--apply' => true];

        $this->artisan('kolabing:auto-kolab', $args)->assertSuccessful();
        $this->artisan('kolabing:auto-kolab', $args)
            ->expectsOutputToContain('already have an open Kolab')
            ->assertFailed();

        $this->assertSame(1, Kolab::query()->count());
    }

    public function test_switched_off_accounts_past_dates_and_wrong_roles_are_refused(): void
    {
        $business = $this->business();
        $community = $this->community(['is_active' => false]);

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->email,
            'community' => $community->id,
            '--apply' => true,
        ])->expectsOutputToContain('switched off')->assertFailed();

        $community->forceFill(['is_active' => true])->save();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $business->email,
            'community' => $community->id,
            '--date' => now()->subDay()->toDateString(),
            '--apply' => true,
        ])->expectsOutputToContain('in the past')->assertFailed();

        $this->artisan('kolabing:auto-kolab', [
            'business' => $community->email,
            'community' => $community->id,
            '--apply' => true,
        ])->expectsOutputToContain('No business')->assertFailed();

        $this->assertSame(0, Kolab::query()->count());
    }

    // ── Admin panel ─────────────────────────────────────────────────────

    public function test_admin_preview_writes_nothing_and_create_records_the_maintainer(): void
    {
        $business = $this->business();
        $community = $this->community();
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.auto-kolabs.index'))
            ->assertOk()
            ->assertSee('Eixample 46')
            ->assertSee('Real Run Club');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.auto-kolabs.preview'), [
                'business' => $business->id,
                'community' => $community->id,
                'title' => 'Sunday run + brunch',
                'notify' => '1',
            ])
            ->assertOk()
            ->assertSee('Sunday run + brunch')
            ->assertSee('Create auto-kolab');

        $this->assertSame(0, Kolab::query()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.auto-kolabs.store'), [
                'business' => $business->id,
                'community' => $community->id,
                'title' => 'Sunday run + brunch',
                'notify' => '0',
            ])
            ->assertRedirect(route('admin.auto-kolabs.index'));

        $kolab = Kolab::query()->sole();
        $this->assertSame('Sunday run + brunch', $kolab->title);
        $this->assertSame($admin->id, $kolab->created_by_admin_id);
        $this->assertCount(0, $this->matchNotifications(), 'Notify unticked.');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.auto-kolabs.index'))
            ->assertSee('Sunday run + brunch');
    }

    public function test_non_maintainers_cannot_reach_auto_kolabs(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.auto-kolabs.store'), [
                'business' => $this->business()->id,
                'community' => $this->community()->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Kolab::query()->count());
    }
}
