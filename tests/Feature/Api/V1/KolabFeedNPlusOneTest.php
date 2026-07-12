<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KolabFeedNPlusOneTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Standalone existence queries against the applications table are the
     * per-row "has this viewer applied?" check behind `negotiation_triggers`.
     * The folded subqueries (withCount / withExists / whereNotExists) all carry
     * `from "kolabs"` as their outer table, so excluding those isolates the N+1.
     */
    private function listenForStandaloneApplicationQueries(int &$counter): void
    {
        DB::listen(function ($query) use (&$counter): void {
            if (str_contains($query->sql, 'from "applications"') && ! str_contains($query->sql, 'from "kolabs"')) {
                $counter++;
            }
        });
    }

    public function test_browse_feed_does_not_run_per_row_application_existence_queries(): void
    {
        $creator = Profile::factory()->business()->create();
        $viewer = Profile::factory()->community()->create();

        Kolab::factory()->count(4)->published()->venuePromotion()->forCreator($creator)->create([
            'negotiation_triggers' => [
                ['condition' => 'Groups of 20+', 'additional_offer' => 'Dessert pairing.'],
            ],
        ]);

        $standaloneApplicationQueries = 0;
        $this->listenForStandaloneApplicationQueries($standaloneApplicationQueries);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs')
            ->assertOk();

        $this->assertCount(4, $response->json('data.data'));
        $this->assertSame(
            0,
            $standaloneApplicationQueries,
            'Browse feed must not fire a per-row applications existence query (N+1).'
        );
    }

    public function test_saved_feed_exposes_negotiation_triggers_via_annotation_for_applied_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $viewer = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'negotiation_triggers' => [
                ['condition' => 'Groups of 20+', 'additional_offer' => 'Dessert pairing.'],
            ],
        ]);

        Application::factory()->forKolab($kolab)->forApplicant($viewer)->create();
        $viewer->savedKolabs()->syncWithoutDetaching([$kolab->id]);

        $standaloneApplicationQueries = 0;
        $this->listenForStandaloneApplicationQueries($standaloneApplicationQueries);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?saved=1')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(
            'Groups of 20+',
            $response->json('data.data.0.negotiation_triggers.0.condition'),
            'A viewer who applied must see negotiation_triggers in the saved feed.'
        );
        $this->assertSame(
            0,
            $standaloneApplicationQueries,
            'Annotated has_applied must serve exposure without a per-row query.'
        );
    }
}
