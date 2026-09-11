<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Matching\CategoryFitMatrix;
use Tests\TestCase;

class CategoryFitMatrixTest extends TestCase
{
    public function test_known_pairing_scores_high(): void
    {
        $this->assertSame(1.0, CategoryFitMatrix::score('food_community', 'cafe'));
    }

    public function test_weak_pairing_scores_low(): void
    {
        $this->assertSame(0.22, CategoryFitMatrix::score('food_community', 'coworking'));
    }

    public function test_unknown_pairing_returns_null_so_the_signal_can_be_skipped(): void
    {
        $this->assertNull(CategoryFitMatrix::score('not_a_type', 'not_a_category'));
    }

    public function test_a_null_on_either_side_returns_null(): void
    {
        $this->assertNull(CategoryFitMatrix::score(null, 'cafe'));
        $this->assertNull(CategoryFitMatrix::score('food_community', null));
        $this->assertNull(CategoryFitMatrix::score(null, null));
    }

    /**
     * Guards the "one silently dropped row degrades Explore's ranking" risk.
     *
     * Deliberately asserts on aggregates rather than a per-row map, so a
     * considered matrix edit updates two numbers instead of fighting a
     * duplicated copy of the table.
     */
    public function test_matrix_keeps_its_measured_shape(): void
    {
        $this->assertCount(7, CategoryFitMatrix::MATRIX);

        $leafCount = array_sum(array_map(
            static fn (array $row): int => count($row),
            CategoryFitMatrix::MATRIX
        ));

        $this->assertSame(36, $leafCount);
    }

    public function test_every_score_is_a_float_above_zero_and_at_most_one(): void
    {
        foreach (CategoryFitMatrix::MATRIX as $communityType => $row) {
            foreach ($row as $businessCategory => $score) {
                $pair = "{$communityType}.{$businessCategory}";

                $this->assertIsFloat($score, "{$pair} must be a float");
                $this->assertGreaterThan(0.0, $score, "{$pair} must be above 0.0");
                $this->assertLessThanOrEqual(1.0, $score, "{$pair} must be at most 1.0");
            }
        }
    }
}
