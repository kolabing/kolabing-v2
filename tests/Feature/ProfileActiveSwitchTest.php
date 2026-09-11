<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureProfileActive;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\Scopes\ActiveProfileScope;
use App\Models\User;
use App\Services\Admin\ManagedProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The global active/passive switch (#254).
 *
 * Two halves, tested separately because they fail separately: *inaccessible*
 * (tokens die, sign-in refused) and *invisible* (gone from the app's reads).
 */
class ProfileActiveSwitchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function service(): ManagedProfileService
    {
        return app(ManagedProfileService::class);
    }

    // ---------------------------------------------------------------- schema

    public function test_a_new_profile_is_active_by_default(): void
    {
        $profile = Profile::factory()->create();

        $this->assertTrue($profile->is_active);
        $this->assertFalse($profile->isDeactivated());
    }

    public function test_is_active_is_not_mass_assignable(): void
    {
        // Same guarantee as is_test_user: only an admin path sets it, never a
        // request payload that happens to carry the key.
        $profile = Profile::factory()->create();

        $profile->fill(['is_active' => false]);

        $this->assertTrue($profile->is_active);
    }

    public function test_is_active_is_hidden_from_serialization(): void
    {
        $profile = Profile::factory()->create();

        $this->assertArrayNotHasKey('is_active', $profile->toArray());
    }

    // --------------------------------------------------------- inaccessible

    public function test_deactivating_revokes_every_token(): void
    {
        $profile = Profile::factory()->create();
        $profile->createToken('mobile');
        $profile->createToken('refresh', ['refresh']);

        $this->assertSame(2, $profile->tokens()->count());

        $this->service()->deactivate($profile);

        $this->assertSame(0, $profile->tokens()->count());
    }

    public function test_an_authenticated_call_from_a_deactivated_profile_is_refused(): void
    {
        $profile = Profile::factory()->create();

        // Sanctum's acting-as bypasses token storage, which is exactly the case
        // the middleware exists for: a session that outlived the revocation.
        $this->actingAs($profile, 'sanctum');
        $profile->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', EnsureProfileActive::CODE);
    }

    public function test_an_active_profile_is_unaffected(): void
    {
        $profile = Profile::factory()->create();

        $this->actingAs($profile, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_login_with_the_right_password_is_refused_and_says_why(): void
    {
        $profile = Profile::factory()->create([
            'email' => 'switched.off@example.com',
            'password' => Hash::make('correct-horse'),
        ]);
        $this->service()->deactivate($profile);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'switched.off@example.com',
            'password' => 'correct-horse',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', EnsureProfileActive::CODE);
    }

    public function test_a_wrong_password_still_reads_as_a_wrong_password(): void
    {
        // Order matters: checking is_active before the password would tell an
        // attacker which addresses exist.
        $profile = Profile::factory()->create([
            'email' => 'switched.off2@example.com',
            'password' => Hash::make('correct-horse'),
        ]);
        $this->service()->deactivate($profile);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'switched.off2@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_reactivating_restores_access_with_nothing_else_to_do(): void
    {
        $profile = Profile::factory()->create([
            'email' => 'back.on@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $this->service()->deactivate($profile);
        $this->service()->activate($profile);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'back.on@example.com',
            'password' => 'correct-horse',
        ])->assertStatus(200);
    }

    // ------------------------------------------------------------- invisible

    public function test_a_deactivated_business_disappears_from_business_profile_reads(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $this->assertTrue(BusinessProfile::query()->whereKey($business->id)->exists());

        $this->service()->deactivate($profile);

        $this->assertFalse(BusinessProfile::query()->whereKey($business->id)->exists());
    }

    public function test_a_deactivated_community_disappears_from_community_profile_reads(): void
    {
        $profile = Profile::factory()->community()->create();
        $community = CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->service()->deactivate($profile);

        $this->assertFalse(CommunityProfile::query()->whereKey($community->id)->exists());
    }

    public function test_the_data_is_hidden_not_destroyed(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $this->service()->deactivate($profile);

        $this->assertTrue(
            BusinessProfile::withInactiveProfiles()->whereKey($business->id)->exists(),
            'Deactivation must hide the row, never delete it.'
        );
        $this->assertNull($profile->fresh()->deleted_at, 'Deactivation is not a soft delete.');
    }

    public function test_reactivating_brings_the_sub_profile_back(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $this->service()->deactivate($profile);
        $this->service()->activate($profile);

        $this->assertTrue(BusinessProfile::query()->whereKey($business->id)->exists());
    }

    public function test_the_profile_active_scope_filters_a_profile_query(): void
    {
        $live = Profile::factory()->create();
        $off = Profile::factory()->create();
        $this->service()->deactivate($off);

        $ids = Profile::query()->active()->pluck('id')->all();

        $this->assertContains($live->id, $ids);
        $this->assertNotContains($off->id, $ids);
    }

    // ----------------------------------------------------------------- admin

    public function test_an_admin_can_switch_an_account_off_and_back_on(): void
    {
        $profile = Profile::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.deactivate', $profile))
            ->assertRedirect();

        $this->assertFalse($profile->fresh()->is_active);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.activate', $profile))
            ->assertRedirect();

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_the_switch_is_maintainer_only(): void
    {
        $profile = Profile::factory()->create();

        $this->post(route('admin.users.deactivate', $profile))->assertRedirect();
        $this->assertTrue($profile->fresh()->is_active);

        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.users.deactivate', $profile))
            ->assertForbidden();

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_the_admin_list_still_shows_a_deactivated_account(): void
    {
        // The one that matters most: an admin who cannot see a switched-off
        // account has no way to switch it back on.
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Switched Off Bar',
        ]);
        $this->service()->deactivate($profile);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->assertStatus(200)
            ->assertSee('Switched Off Bar')
            ->assertSee('Passive');
    }

    public function test_the_admin_escape_hatch_is_the_only_way_past_the_scope(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $this->service()->deactivate($profile);

        $this->assertSame(0, BusinessProfile::query()->count());
        $this->assertSame(1, BusinessProfile::withInactiveProfiles()->count());
        $this->assertSame(
            1,
            BusinessProfile::query()->withoutGlobalScope(ActiveProfileScope::class)->count()
        );
    }
}
