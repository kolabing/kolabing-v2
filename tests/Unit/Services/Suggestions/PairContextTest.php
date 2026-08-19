<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Services\Suggestions\PairContext;
use InvalidArgumentException;
use Tests\TestCase;

class PairContextTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function context(array $overrides = []): PairContext
    {
        return new PairContext(...array_merge([
            'audience' => SuggestionAudience::Business,
            'viewerProfileId' => 'viewer',
            'counterpartProfileId' => 'counterpart',
            'communityType' => 'food_community',
            'businessCategories' => ['cafe'],
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-1',
            'distanceKm' => 2.0,
            'pastAttendance' => [40, 45, 50],
            'communitySize' => 120,
            'venueCapacity' => 45,
            'viewerOffers' => ['food_drink', 'venue'],
            'counterpartNeeds' => ['food_drink'],
            'averageRating' => 4.6,
            'repeatRatio' => 0.9,
            'contentDelivered' => 5,
            'completedCollaborations' => 0,
            'reviewCount' => 4,
            'recentEventCount' => 3,
            'hasActiveSeries' => true,
        ], $overrides));
    }

    public function test_a_valid_pair_is_accepted(): void
    {
        $context = $this->context();

        $this->assertSame('viewer', $context->viewerProfileId);
        $this->assertSame('counterpart', $context->counterpartProfileId);
    }

    /**
     * `averageRating` and `repeatRatio` are adjacent nullable floats with
     * different ranges, so a swap at the single call site would push a 4.6
     * rating through the repeat term and out the other side as a score above
     * 100. Nothing downstream could tell.
     */
    public function test_a_repeat_ratio_outside_zero_to_one_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PairContext [repeatRatio]');

        $this->context(['averageRating' => 0.9, 'repeatRatio' => 4.6]);
    }

    public function test_an_average_rating_outside_zero_to_five_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PairContext [averageRating]');

        $this->context(['averageRating' => 6.2]);
    }

    /**
     * A past event whose `attendee_count` was never filled in is not evidence of
     * scale: medianing it would render "expect around 0 people".
     */
    public function test_past_attendance_may_not_carry_unreported_events(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PairContext [pastAttendance]');

        $this->context(['pastAttendance' => [0, 0, 0]]);
    }

    public function test_a_negative_count_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PairContext [reviewCount]');

        $this->context(['reviewCount' => -1]);
    }

    public function test_an_empty_profile_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PairContext [counterpartProfileId]');

        $this->context(['counterpartProfileId' => '  ']);
    }
}
