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
        $this->assertLessThan(0.5, CategoryFitMatrix::score('food_community', 'coworking'));
    }

    public function test_unknown_pairing_returns_null_so_the_signal_can_be_skipped(): void
    {
        $this->assertNull(CategoryFitMatrix::score('not_a_type', 'not_a_category'));
    }
}
