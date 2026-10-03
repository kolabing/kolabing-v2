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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A soft-deleted owner is as gone as a switched-off one (BE-FX-75).
 *
 * ActiveOwnerScope checked only `profiles.is_active`, and deleting an account
 * does not clear it. A deleted owner with `is_active = true` therefore passed
 * fromActiveOwner(), and their kolab reached Explore with no owner: "— (—)" in
 * the admin panel, "Unknown" on the card. On 28 Sep, production had 24 such
 * profiles.
 */
class DeletedOwnerContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_deleted_businesss_kolab_leaves_explore_even_while_is_active_is_still_true(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $viewer->id]);

        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($business)->create([
            'availability_start' => null,
            'availability_end' => null,
        ]);

        $this->assertContains($kolab->id, $this->exploreKolabIds($viewer));

        $business->delete();
        $this->assertTrue(
            Profile::withTrashed()->findOrFail($business->id)->is_active,
            'Deleting an account leaves is_active = true — the case the scope missed.'
        );

        $this->assertSame(0, Kolab::query()->fromActiveOwner()->count());
        $this->assertNotContains($kolab->id, $this->exploreKolabIds($viewer));
    }

    public function test_a_deleted_owners_community_and_events_leave_ordinary_reads(): void
    {
        $owner = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $owner->id]);
        $community = Community::factory()->create(['owner_profile_id' => $owner->id]);
        Event::factory()->create(['profile_id' => $owner->id, 'community_id' => $community->id]);

        $this->assertSame(1, Community::query()->count());
        $this->assertSame(1, Event::query()->count());

        $owner->delete();

        $this->assertSame(0, Community::query()->count());
        $this->assertSame(0, Event::query()->count());
    }

    public function test_the_applicant_keeps_its_application_when_the_creator_is_deleted(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        $kolab = Kolab::factory()->create(['creator_profile_id' => $business->id]);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
        ]);

        $business->delete();

        $this->assertNotNull($application->fresh()->kolab);
        $this->assertSame(1, Application::query()->whereHas('kolab')->count());
    }

    /**
     * @return list<string>
     */
    private function exploreKolabIds(Profile $viewer): array
    {
        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/discovery/opportunities?feed=all&per_page=50&city=all')
            ->assertOk();

        return collect($response->json('data.data'))
            ->where('item_type', '!=', 'multi_kolab_role')
            ->pluck('id')
            ->values()
            ->all();
    }
}
