<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\SuggestionAudience;
use App\Enums\UserType;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabSuggestionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_live_scope_excludes_dismissed_expired_and_converted_rows(): void
    {
        $live = KolabSuggestion::factory()->create();
        KolabSuggestion::factory()->dismissed()->create();
        KolabSuggestion::factory()->expired()->create();
        KolabSuggestion::factory()->converted()->create();

        $ids = KolabSuggestion::query()->live()->pluck('id')->all();

        $this->assertSame([$live->id], $ids);
    }

    public function test_json_columns_round_trip_as_arrays(): void
    {
        $suggestion = KolabSuggestion::factory()->create();

        $fresh = $suggestion->fresh();

        $this->assertIsArray($fresh->signals);
        $this->assertSame('category_fit', $fresh->signals[0]['key']);
        $this->assertSame(40, $fresh->suggested_format['expected_attendance']);
    }

    public function test_audience_casts_to_an_enum_and_mirrors_the_viewer_role(): void
    {
        $suggestion = KolabSuggestion::factory()->forCommunityAudience()->create();

        $fresh = $suggestion->fresh();

        $this->assertSame(SuggestionAudience::Community, $fresh->audience);
        $this->assertSame(UserType::Community, $fresh->viewerProfile->user_type);
        $this->assertSame(UserType::Business, $fresh->counterpartProfile->user_type);
    }

    public function test_for_pair_derives_the_audience_from_the_viewers_role(): void
    {
        $community = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $toCommunity = KolabSuggestion::factory()->forPair($community, $business)->create();
        $toBusiness = KolabSuggestion::factory()->forPair($business, $community)->create();

        $this->assertSame(SuggestionAudience::Community, $toCommunity->audience);
        $this->assertSame($community->id, $toCommunity->viewer_profile_id);
        $this->assertSame(SuggestionAudience::Business, $toBusiness->audience);
        $this->assertSame($business->id, $toBusiness->viewer_profile_id);
    }

    public function test_for_viewer_scope_returns_only_the_viewers_own_rows(): void
    {
        $mine = KolabSuggestion::factory()->create();
        KolabSuggestion::factory()->create();

        $ids = KolabSuggestion::query()->forViewer($mine->viewerProfile)->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_a_pair_may_only_hold_one_row(): void
    {
        $suggestion = KolabSuggestion::factory()->create();

        $this->expectException(QueryException::class);

        KolabSuggestion::factory()->create([
            'viewer_profile_id' => $suggestion->viewer_profile_id,
            'counterpart_profile_id' => $suggestion->counterpart_profile_id,
        ]);
    }
}
