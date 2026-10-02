<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\KolabInvite;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kolabing invites communities to apply to a Kolab by in-app + push
 * notification (Daniel 2026-10-02).
 */
class KolabInviteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function city(string $name = 'Barcelona'): City
    {
        return City::query()->where('name', $name)->first()
            ?? City::factory()->create(['name' => $name, 'country' => 'Spain', 'is_active' => true]);
    }

    private function offer(array $overrides = []): Kolab
    {
        $business = Profile::factory()->business()->create(['email' => 'owner@cafe.test']);
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Cafe Mallorca',
            'city_id' => $this->city()->id,
            'city_name' => 'Barcelona',
        ]);

        return Kolab::factory()->venuePromotion()->published()->create([
            'creator_profile_id' => $business->id,
            'title' => 'Host your community at Cafe Mallorca',
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
            'is_auto_listing' => true,
            ...$overrides,
        ]);
    }

    private function community(string $name, array $profile = [], array $community = []): Profile
    {
        $p = Profile::factory()->community()->create(['preferred_locale' => 'en', ...$profile]);
        CommunityProfile::factory()->create([
            'profile_id' => $p->id,
            'name' => $name,
            'city_id' => $this->city()->id,
            'community_type' => 'run_club',
            'community_size' => 100,
            ...$community,
        ]);

        return $p->fresh();
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_command_is_a_dry_run_by_default(): void
    {
        $kolab = $this->offer();
        $this->community('Real Run Club');

        $this->artisan('kolabing:invite-communities', ['kolab' => $kolab->id])
            ->expectsOutputToContain('Real Run Club')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, KolabInvite::query()->count());
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_apply_invites_matching_communities_with_a_notification_that_opens_the_kolab(): void
    {
        $kolab = $this->offer();
        $runners = $this->community('Real Run Club');
        $this->community('Madrid Runners', community: ['city_id' => $this->city('Madrid')->id]);
        $this->community('Photo Walks', community: ['community_type' => 'photography_community']);

        $this->artisan('kolabing:invite-communities', [
            'kolab' => 'owner@cafe.test',
            '--category' => 'run_club',
            '--apply' => true,
        ])->assertSuccessful();

        $invite = KolabInvite::query()->sole();
        $this->assertSame($runners->id, $invite->profile_id);
        $this->assertSame($kolab->id, $invite->kolab_id);

        $notification = Notification::query()->sole();
        $this->assertSame($runners->id, $notification->profile_id);
        $this->assertSame($invite->notification_id, $notification->id);
        $this->assertSame(NotificationType::KolabInvite, $notification->type);
        $this->assertSame('kolab', $notification->target_type);
        $this->assertSame($kolab->id, $notification->target_id);
        $this->assertSame("You're invited to a Kolab", $notification->title);
        $this->assertStringContainsString('Cafe Mallorca', $notification->body);

        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->type === NotificationType::KolabInvite && $job->targetId === $kolab->id);
    }

    public function test_push_deep_links_to_the_kolab_detail_route(): void
    {
        $method = new \ReflectionMethod(PushNotificationService::class, 'resolveDeeplink');

        $this->assertSame(
            '/opportunity/abc',
            $method->invoke(app(PushNotificationService::class), NotificationType::KolabInvite, 'abc'),
        );
    }

    public function test_a_community_is_invited_to_a_kolab_at_most_once(): void
    {
        $kolab = $this->offer();
        $this->community('Real Run Club');

        $this->artisan('kolabing:invite-communities', ['kolab' => $kolab->id, '--apply' => true])->assertSuccessful();
        $this->artisan('kolabing:invite-communities', ['kolab' => $kolab->id, '--apply' => true])->assertSuccessful();

        $this->assertSame(1, KolabInvite::query()->count());
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_weekly_cap_limit_and_largest_first(): void
    {
        $small = $this->community('Small Club', community: ['community_size' => 10]);
        $big = $this->community('Big Club', community: ['community_size' => 5000]);
        $busy = $this->community('Busy Club', community: ['community_size' => 9000]);

        foreach ([$this->offer(['title' => 'One']), $this->offerFromAnotherBusiness('Two')] as $other) {
            KolabInvite::query()->create(['kolab_id' => $other->id, 'profile_id' => $busy->id]);
        }

        $kolab = $this->offerFromAnotherBusiness('Three');

        $this->artisan('kolabing:invite-communities', [
            'kolab' => $kolab->id,
            '--limit' => 1,
            '--weekly-cap' => 2,
            '--apply' => true,
        ])->assertSuccessful();

        $invited = KolabInvite::query()->where('kolab_id', $kolab->id)->pluck('profile_id')->all();
        $this->assertSame([$big->id], $invited, 'Busy Club hit the weekly cap; the limit takes the largest of the rest.');
        $this->assertNotContains($small->id, $invited);
    }

    public function test_applied_blocked_switched_off_and_test_communities_are_skipped(): void
    {
        $kolab = $this->offer();
        $applied = $this->community('Applied Club');
        Application::factory()->pending()->create(['kolab_id' => $kolab->id, 'applicant_profile_id' => $applied->id]);
        $blocked = $this->community('Blocked Club');
        UserBlock::query()->create(['blocker_profile_id' => $blocked->id, 'blocked_profile_id' => $kolab->creator_profile_id]);
        $this->community('Off Club', profile: ['is_active' => false]);
        $this->community('Test Club', profile: ['is_test_user' => true]);
        $ok = $this->community('Real Run Club');

        $this->artisan('kolabing:invite-communities', ['kolab' => $kolab->id, '--apply' => true])->assertSuccessful();

        $this->assertSame([$ok->id], KolabInvite::query()->pluck('profile_id')->all());
    }

    public function test_push_respects_the_community_switch_but_the_in_app_row_is_written(): void
    {
        $kolab = $this->offer();
        $community = $this->community('Quiet Club');
        NotificationPreference::factory()->create(['profile_id' => $community->id, 'collaboration_updates' => false]);

        $this->artisan('kolabing:invite-communities', ['kolab' => $kolab->id, '--apply' => true])->assertSuccessful();

        $this->assertSame(1, Notification::query()->where('profile_id', $community->id)->count());
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_kolabs_communities_cannot_apply_to_are_refused(): void
    {
        $this->community('Real Run Club');

        $direct = $this->offer(['recipient_community_id' => Profile::factory()->community()->create()->id]);
        $this->artisan('kolabing:invite-communities', ['kolab' => $direct->id, '--apply' => true])
            ->expectsOutputToContain('direct proposal')
            ->assertFailed();

        $closed = $this->offerFromAnotherBusiness('Closed', ['status' => KolabStatus::Closed]);
        $this->artisan('kolabing:invite-communities', ['kolab' => $closed->id, '--apply' => true])
            ->expectsOutputToContain('not published')
            ->assertFailed();

        $this->assertSame(0, KolabInvite::query()->count());
    }

    public function test_admin_preview_then_send(): void
    {
        $kolab = $this->offer();
        $community = $this->community('Real Run Club');
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.kolab-invites.index', ['kolab' => $kolab->id]))
            ->assertOk()
            ->assertSee('Host your community at Cafe Mallorca');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.kolab-invites.preview'), ['kolab' => $kolab->id])
            ->assertOk()
            ->assertSee('Real Run Club')
            ->assertSee('Send 1 invite');

        $this->assertSame(0, KolabInvite::query()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.kolab-invites.store'), ['kolab' => $kolab->id, 'city' => 'Barcelona'])
            ->assertRedirect(route('admin.kolab-invites.index', ['kolab' => $kolab->id]));

        $invite = KolabInvite::query()->sole();
        $this->assertSame($community->id, $invite->profile_id);
        $this->assertSame($admin->id, $invite->sent_by_admin_id);
    }

    public function test_non_maintainers_cannot_send_invites(): void
    {
        $kolab = $this->offer();
        $this->community('Real Run Club');

        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.kolab-invites.store'), ['kolab' => $kolab->id])
            ->assertForbidden();

        $this->assertSame(0, KolabInvite::query()->count());
    }

    private function offerFromAnotherBusiness(string $title, array $overrides = []): Kolab
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id, 'city_id' => $this->city()->id]);

        return Kolab::factory()->venuePromotion()->published()->create([
            'creator_profile_id' => $business->id,
            'title' => $title,
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
            ...$overrides,
        ]);
    }
}
