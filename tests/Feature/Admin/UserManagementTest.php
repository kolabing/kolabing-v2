<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_maintainer_can_soft_delete_a_user(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.users.destroy', $profile))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('profiles', ['id' => $profile->id]);
    }

    public function test_maintainer_can_grant_subscription_to_a_business_user(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.subscription.grant', $profile))
            ->assertRedirect();

        $subscription = BusinessSubscription::where('profile_id', $profile->id)->firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(SubscriptionSource::Maintainer, $subscription->source);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertTrue($subscription->current_period_end->isAfter(now()->addMonths(11)));
    }

    public function test_granting_subscription_updates_existing_subscription_row(): void
    {
        $profile = Profile::factory()->business()->create();

        // The factory creates an inactive Stripe sub; granting should flip it.
        BusinessSubscription::firstOrCreate(
            ['profile_id' => $profile->id],
            ['source' => SubscriptionSource::Stripe, 'status' => SubscriptionStatus::Inactive],
        );

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.subscription.grant', $profile))
            ->assertRedirect();

        $this->assertDatabaseCount('business_subscriptions', 1);
        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'status' => 'active',
            'source' => 'maintainer',
        ]);
    }

    public function test_maintainer_can_revoke_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessSubscription::firstOrCreate(
            ['profile_id' => $profile->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.subscription.revoke', $profile))
            ->assertRedirect();

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'status' => 'inactive',
        ]);
    }

    public function test_granting_subscription_to_non_business_user_is_rejected(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.subscription.grant', $profile))
            ->assertStatus(422);

        $this->assertDatabaseMissing('business_subscriptions', ['profile_id' => $profile->id]);
    }

    public function test_users_index_renders_action_buttons(): void
    {
        Profile::factory()->business()->create();
        Profile::factory()->community()->create();

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Grant sub', false);
        $response->assertSee('Delete', false);
    }

    public function test_admin_routes_reject_non_maintainer(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);
        $profile = Profile::factory()->business()->create();

        $this->actingAs($user, 'admin')
            ->delete(route('admin.users.destroy', $profile))
            ->assertForbidden();
    }
}
