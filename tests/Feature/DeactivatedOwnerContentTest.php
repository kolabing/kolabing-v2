<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\Community;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\Admin\ManagedProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * What a deactivated owner's content does (#258).
 *
 * #254 scoped the sub-profiles and stopped there, so the *profile* was hidden
 * but the things it owns were not: `/communities/discover` still returned a
 * deactivated community, and — worse — with a blank name, because the row
 * survived while its eager-loaded profile was scoped away.
 *
 * Two halves, and the second is the one that constrains the first: content must
 * disappear from discovery, but a counterparty who is still active must never
 * lose their own record because the other side was switched off.
 */
class DeactivatedOwnerContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): ManagedProfileService
    {
        return app(ManagedProfileService::class);
    }

    // ------------------------------------------- the leak, closed

    public function test_a_deactivated_community_leaves_discovery(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        $owner = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $owner->id, 'name' => 'Ghost Runners']);
        Community::factory()->create([
            'owner_profile_id' => $owner->id,
            'name' => 'Ghost Runners',
            'join_policy' => 'open',
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/communities/discover')
            ->assertStatus(200)
            ->assertSee('Ghost Runners');

        $this->service()->deactivate($owner);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/communities/discover');

        $response->assertStatus(200);
        $this->assertStringNotContainsString(
            'Ghost Runners',
            $response->getContent() ?: '',
            'A switched-off community must be absent, not present with a blank name.'
        );
    }

    public function test_a_deactivated_owners_community_and_events_leave_ordinary_reads(): void
    {
        $owner = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $owner->id]);
        $community = Community::factory()->create(['owner_profile_id' => $owner->id]);
        Event::factory()->create(['profile_id' => $owner->id, 'community_id' => $community->id]);

        $this->assertSame(1, Community::query()->count());
        $this->assertSame(1, Event::query()->count());

        $this->service()->deactivate($owner);

        $this->assertSame(0, Community::query()->count());
        $this->assertSame(0, Event::query()->count());
    }

    public function test_a_deactivated_businesss_kolab_leaves_explore(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $viewer->id]);

        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        Kolab::factory()->create(['creator_profile_id' => $business->id]);

        $this->assertSame(1, Kolab::query()->fromActiveOwner()->count());

        $this->service()->deactivate($business);

        $this->assertSame(0, Kolab::query()->fromActiveOwner()->count());
    }

    // ------------------------- the constraint: the counterparty keeps their record

    public function test_an_active_applicant_keeps_its_application_when_the_creator_is_switched_off(): void
    {
        // Measured before the fix: a global scope on Kolab made
        // `application->kolab` null and `whereHas('kolab')` return 0, so the
        // community lost its own sent application. Hiding a business must never
        // cost the other party their record.
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        $kolab = Kolab::factory()->create(['creator_profile_id' => $business->id]);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
        ]);

        $this->service()->deactivate($business);

        $this->assertNotNull(
            $application->fresh()->kolab,
            'The applicant must still be able to read the kolab they applied to.'
        );
        $this->assertSame(
            1,
            Application::query()->whereHas('kolab')->count(),
            'whereHas(kolab) must still match — this is what the sent-applications list runs.'
        );
        $this->assertSame(1, Kolab::query()->count(), 'Kolab carries no global scope, by design.');
    }

    // ------------------------------------------------- reversibility & admin

    public function test_reactivating_brings_the_content_back(): void
    {
        $owner = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $owner->id]);
        $community = Community::factory()->create(['owner_profile_id' => $owner->id]);
        Event::factory()->create(['profile_id' => $owner->id, 'community_id' => $community->id]);

        $this->service()->deactivate($owner);
        $this->service()->activate($owner);

        $this->assertSame(1, Community::query()->count());
        $this->assertSame(1, Event::query()->count());
    }

    public function test_the_content_is_hidden_not_deleted(): void
    {
        $owner = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $owner->id]);
        Community::factory()->create(['owner_profile_id' => $owner->id]);

        $this->service()->deactivate($owner);

        $this->assertSame(0, Community::query()->count());
        $this->assertSame(1, Community::withInactiveOwners()->count());
    }
}
