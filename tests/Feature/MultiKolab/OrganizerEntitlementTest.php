<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\OrganizerCapability;
use App\Models\OrganizerEntitlement;
use App\Models\Profile;
use App\Models\User;
use App\Services\KolabService;
use App\Services\OrganizerEntitlementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganizerEntitlementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function service(): OrganizerEntitlementService
    {
        return app(OrganizerEntitlementService::class);
    }

    // --- Profile::hasEventCreatorEntitlement() -----------------------------

    public function test_business_profile_has_no_entitlement_by_default(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->assertFalse($profile->hasEventCreatorEntitlement());
    }

    public function test_community_profile_has_no_entitlement_by_default(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->assertFalse($profile->hasEventCreatorEntitlement());
    }

    public function test_granting_gives_a_business_profile_the_entitlement(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->service()->grant($profile);

        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_granting_gives_a_community_profile_the_entitlement(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service()->grant($profile);

        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_revoking_removes_the_entitlement(): void
    {
        $profile = Profile::factory()->business()->create();
        $this->service()->grant($profile);
        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());

        $this->service()->revoke($profile);

        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_expired_entitlement_does_not_count(): void
    {
        $profile = Profile::factory()->community()->create();
        OrganizerEntitlement::factory()
            ->for($profile, 'profile')
            ->expired()
            ->create(['capability' => OrganizerCapability::EventCreator]);

        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_grant_is_idempotent_and_reactivates_a_revoked_entitlement(): void
    {
        $profile = Profile::factory()->business()->create();
        $this->service()->grant($profile);
        $this->service()->revoke($profile);
        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());

        $this->service()->grant($profile);

        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());
        $this->assertSame(
            1,
            OrganizerEntitlement::query()->where('profile_id', $profile->id)->count(),
            'Re-granting should reuse the existing row, not create a duplicate.',
        );
    }

    // --- Critical regression: ordinary Community Kolab access is unaffected

    public function test_community_without_entitlement_can_still_create_and_publish_an_ordinary_kolab(): void
    {
        $community = Profile::factory()->community()->create();
        $this->assertFalse($community->hasEventCreatorEntitlement());

        $kolab = app(KolabService::class)->create($community, [
            'intent_type' => IntentType::CommunitySeeking->value,
            'title' => 'Saturday Run Club Kolab',
            'description' => 'Looking for a venue partner.',
            'preferred_city' => 'Barcelona',
        ]);

        $published = app(KolabService::class)->publish($kolab);

        $this->assertSame(KolabStatus::Published, $published->status);
    }

    // --- Maintainer admin grant/revoke surface ------------------------------

    public function test_maintainer_can_grant_event_creator_entitlement_to_a_business(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.event-creator.grant', $profile))
            ->assertRedirect();

        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_maintainer_can_grant_event_creator_entitlement_to_a_community(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.event-creator.grant', $profile))
            ->assertRedirect();

        $this->assertTrue($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_maintainer_can_revoke_event_creator_entitlement(): void
    {
        $profile = Profile::factory()->business()->create();
        $this->service()->grant($profile);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.event-creator.revoke', $profile))
            ->assertRedirect();

        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_non_maintainer_admin_cannot_grant_event_creator_entitlement(): void
    {
        $profile = Profile::factory()->business()->create();
        $nonMaintainer = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($nonMaintainer, 'admin')
            ->post(route('admin.users.event-creator.grant', $profile))
            ->assertForbidden();

        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());
    }

    public function test_grant_route_requires_admin_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->post(route('admin.users.event-creator.grant', $profile))
            ->assertRedirect(route('login'));

        $this->assertFalse($profile->fresh()->hasEventCreatorEntitlement());
    }
}
