<?php

declare(strict_types=1);

namespace App\Services\SalesOutreach;

use App\Models\Profile;

/**
 * The number the pitch leads with, and the arithmetic behind it.
 *
 * Kolabing never sees a venue's till, so this cannot be a measurement and must not
 * be dressed as one. What it is: a community's stated membership, discounted to a
 * plausible turnout, times an average spend the salesperson sets per pitch. Both
 * inputs travel with the result — into the draft row, onto the review screen, and
 * into the email itself — because the first question a business asks about a
 * revenue claim is where it came from, and "the AI wrote it" is not an answer.
 *
 * Everything here is deliberately plain arithmetic in PHP. Letting the language
 * model produce the revenue figure would make the one number with real commercial
 * consequences the least predictable thing in the email.
 */
class RevenueEstimator
{
    /**
     * Turnout to suggest on the form for this community.
     *
     * Clamped at both ends: a community that reports 40,000 members would otherwise
     * produce a pitch promising thousands through the door of a 40-seat café, and a
     * community with no stated size at all would produce zero — which reads as "we
     * will bring you nobody".
     */
    public function suggestedAttendees(Profile $community): int
    {
        $size = (int) ($community->communityProfile?->community_size ?? 0);

        $rate = (float) config('sales_outreach.revenue.attendance_rate');
        $min = (int) config('sales_outreach.revenue.min_attendees');
        $max = (int) config('sales_outreach.revenue.max_attendees');

        // No stated size is not evidence of a small community — it is evidence of an
        // unfilled field, so fall back to the floor rather than to zero.
        $estimate = $size > 0 ? (int) round($size * $rate) : $min;

        return max($min, min($max, $estimate));
    }

    public function defaultAvgSpendCents(): int
    {
        return (int) config('sales_outreach.revenue.avg_spend_cents');
    }

    /**
     * Attendees × average spend. That is the whole model, and its transparency is
     * the point: every figure in the email can be re-derived by the person reading it.
     */
    public function estimateCents(int $attendees, int $avgSpendCents): int
    {
        return max(0, $attendees) * max(0, $avgSpendCents);
    }
}
