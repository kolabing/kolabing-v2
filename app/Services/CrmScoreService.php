<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmAccount;
use App\Models\CrmScoreWeight;

/**
 * Computes a CRM account's score from its metrics + the admin-adjustable
 * crm_score_weights.
 *
 * - business: Y/N fit factors → sum of points (capped at 100).
 * - community: Y/N fit factors → sum of points (capped at 100), same shape
 *   as business but with its own weight set.
 * - ambassador: contribution = sum(count × points per metric), uncapped.
 */
class CrmScoreService
{
    /** Default weights, seeded into crm_score_weights (admin can edit). */
    public const DEFAULTS = [
        'business' => [
            'active_ig' => ['Active IG', 10],
            'hosts_events' => ['Hosts events', 20],
            'community_friendly' => ['Community friendly', 20],
            'multiple_locations' => ['Multiple locations', 10],
            'good_fit' => ['Good Kolab fit', 20],
            'responsive' => ['Responsive', 20],
        ],
        'ambassador' => [
            'businesses_referred' => ['Businesses referred', 20],
            'communities_referred' => ['Communities referred', 15],
            'kolabs_generated' => ['Kolabs generated', 30],
            'product_feedback' => ['Product feedback', 10],
            'feature_suggestions' => ['Feature suggestions', 5],
        ],
        'community' => [
            'events_weekly' => ['Events weekly', 20],
            'strong_attendance' => ['Strong attendance', 20],
            'active_ig' => ['Active Instagram', 15],
            'engaged_founder' => ['Engaged founder', 20],
            'good_vibes' => ['Good vibes', 25],
        ],
    ];

    /**
     * @return array<string, int> key => points
     */
    public function weightsFor(string $type): array
    {
        $rows = CrmScoreWeight::query()->where('applies_to', $type)->pluck('points', 'key')->all();
        if ($rows !== []) {
            return array_map('intval', $rows);
        }

        // Fall back to defaults if the weights table hasn't been seeded.
        return array_map(static fn (array $d): int => $d[1], self::DEFAULTS[$type] ?? []);
    }

    public function score(CrmAccount $account): int
    {
        $metrics = $account->metrics ?? [];

        // business + community share the Y/N-factor → /100 shape.
        if ($account->type === 'business' || $account->type === 'community') {
            $total = 0;
            foreach ($this->weightsFor($account->type) as $key => $points) {
                if (! empty($metrics[$key])) { // Y / true
                    $total += $points;
                }
            }

            return min(100, $total);
        }

        if ($account->type === 'ambassador') {
            $total = 0;
            foreach ($this->weightsFor('ambassador') as $key => $points) {
                $total += ((int) ($metrics[$key] ?? 0)) * $points;
            }

            return $total;
        }

        return 0; // unknown type
    }

    public function recalculate(CrmAccount $account): CrmAccount
    {
        $account->score = $this->score($account);
        $account->save();

        return $account;
    }
}
