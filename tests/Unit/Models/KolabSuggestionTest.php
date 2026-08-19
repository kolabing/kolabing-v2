<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\KolabSuggestion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabSuggestionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_live_scope_excludes_dismissed_and_expired_rows(): void
    {
        $live = KolabSuggestion::factory()->create();
        KolabSuggestion::factory()->dismissed()->create();
        KolabSuggestion::factory()->expired()->create();

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
}
