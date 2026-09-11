<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PhotoOrderingService;
use PHPUnit\Framework\TestCase;

class PhotoOrderingServiceTest extends TestCase
{
    /**
     * @param  array<int, string>  $requested
     * @param  array<int, string>  $owned
     * @return array<int, string>
     */
    private function order(array $requested, array $owned): array
    {
        return (new PhotoOrderingService)->resolve($requested, $owned);
    }

    public function test_it_orders_by_the_requested_sequence(): void
    {
        $this->assertSame(['c', 'a', 'b'], $this->order(['c', 'a', 'b'], ['a', 'b', 'c']));
    }

    public function test_ids_that_are_not_owned_are_ignored(): void
    {
        // A caller must never reorder someone else's photo by guessing an id.
        $this->assertSame(['b', 'a'], $this->order(['b', 'intruder', 'a'], ['a', 'b']));
    }

    public function test_owned_ids_missing_from_the_request_keep_their_relative_order_at_the_end(): void
    {
        // A partial list must never make a photo disappear from the gallery.
        $this->assertSame(['c', 'a', 'b', 'd'], $this->order(['c'], ['a', 'b', 'c', 'd']));
    }

    public function test_duplicate_ids_in_the_request_are_collapsed(): void
    {
        $this->assertSame(['b', 'a'], $this->order(['b', 'b', 'a'], ['a', 'b']));
    }

    public function test_an_empty_request_leaves_the_existing_order_untouched(): void
    {
        $this->assertSame(['a', 'b'], $this->order([], ['a', 'b']));
    }

    public function test_non_string_entries_are_ignored(): void
    {
        $this->assertSame(['a', 'b'], $this->order([null, 42, 'a'], ['a', 'b']));
    }
}
