<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The collaborations list nests KolabResource + OpportunitySummaryResource for
 * each row. Both resolve the viewer-scoped `is_saved` flag, KolabResource also
 * resolves `has_applied`, and both render the kolab's creator profile. Without
 * annotation/eager-loading these fire per row. This guards that the list issues
 * a constant number of queries regardless of row count.
 */
class CollaborationListNestedKolabNPlusOneTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function countQueries(\Closure $fn): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return Profile the community applicant who sees the collaborations
     */
    private function communityWith(int $collaborations): Profile
    {
        $viewer = Profile::factory()->community()->create();

        for ($i = 0; $i < $collaborations; $i++) {
            $business = Profile::factory()->business()->create();
            // VenuePromotion (not CommunitySeeking) so KolabResource's
            // negotiation_triggers/has_applied path is active for a non-creator viewer.
            $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($business)->create();
            Collaboration::factory()->completed()
                ->forCreator($business)
                ->forApplicant($viewer)
                ->create(['kolab_id' => $kolab->id]);
        }

        return $viewer;
    }

    public function test_collaborations_list_query_count_is_constant_across_rows(): void
    {
        // Build data OUTSIDE the measurement window so only the GET is counted.
        $viewerOne = $this->communityWith(1);
        $viewerMany = $this->communityWith(3);

        $baseline = $this->countQueries(fn () => $this->actingAs($viewerOne)
            ->getJson('/api/v1/collaborations')->assertOk());
        $scaled = $this->countQueries(fn () => $this->actingAs($viewerMany)
            ->getJson('/api/v1/collaborations')->assertOk());

        $this->assertSame($baseline, $scaled,
            "Collaborations list must not fire per-row queries for the nested kolab's is_saved / has_applied / creator profile (1 row: {$baseline}, 3 rows: {$scaled}).");
    }
}
