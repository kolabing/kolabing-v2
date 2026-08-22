<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmAccount;
use App\Models\CrmScoreWeight;
use Carbon\CarbonImmutable;

/**
 * Computes a CRM account's score from its metrics + the admin-adjustable
 * crm_score_weights.
 *
 * - business: Y/N fit factors → sum of points (capped at 100).
 * - community: for the Challenge-A verified set (rows carrying `audience_count`)
 *   a FIT model over real supply signals (audience, collabs, recency, confidence,
 *   locality); legacy Y/N-factor communities keep the old sum. A manual
 *   `metrics.fit_override` (0-100) always wins.
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

        return array_map(static fn (array $d): int => $d[1], self::DEFAULTS[$type] ?? []);
    }

    public function score(CrmAccount $account): int
    {
        $metrics = $account->metrics ?? [];

        // Manual override always wins (GTM pins a lead).
        if (isset($metrics['fit_override']) && is_numeric($metrics['fit_override'])) {
            return max(0, min(100, (int) $metrics['fit_override']));
        }

        // Verified community → real supply-signal fit model.
        if ($account->type === 'community' && array_key_exists('audience_count', $metrics)) {
            return $this->communityFit($metrics)['score'];
        }

        if ($account->type === 'business' || $account->type === 'community') {
            $total = 0;
            foreach ($this->weightsFor($account->type) as $key => $points) {
                if (! empty($metrics[$key])) {
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

        return 0;
    }

    /**
     * Fit for a verified community (0-100) + an explainable breakdown.
     * Signals: audience (log-scaled, capped), has-collab (demonstrated behaviour),
     * recency of last event, confidence (scaler), locality/global-brand (gate).
     *
     * @param  array<string, mixed>  $metrics
     * @return array{score:int, breakdown:array<string,int|string>}
     */
    public function communityFit(array $metrics): array
    {
        $b = [];

        $aud = (int) ($metrics['audience_count'] ?? 0);
        $b['audience'] = $aud > 0 ? (int) min(30, round(log10($aud) * 9)) : 0;

        $collab = trim((string) ($metrics['collab_businesses'] ?? ''));
        $b['collab'] = ($collab !== '' && stripos($collab, 'n/f') === false) ? 20 : 0;

        $days = $this->daysSince($metrics['last_active_date'] ?? null);
        $b['recency'] = $days === null ? 0 : ($days <= 30 ? 25 : ($days <= 90 ? 15 : 0));

        $base = $b['audience'] + $b['collab'] + $b['recency'];

        // Confidence scales the whole base (discount low-confidence rows).
        $conf = strtolower((string) ($metrics['confidence'] ?? ''));
        $mult = str_starts_with($conf, 'high') ? 1.0 : (str_starts_with($conf, 'med') ? 0.85 : 0.65);
        $score = (int) round($base * $mult);

        // Locality gate: a global brand / non-local chapter can never rank top-decile.
        if (! empty($metrics['is_global_brand']) || ($metrics['locality_confirmed'] ?? true) === false) {
            $score = min($score, 20);
            $b['locality_cap'] = 20;
        }

        $b['confidence_mult'] = $conf ?: 'med';

        return ['score' => max(0, min(100, $score)), 'breakdown' => $b];
    }

    private function daysSince(mixed $date): ?int
    {
        if (! $date) {
            return null;
        }
        try {
            return (int) CarbonImmutable::parse((string) $date)->diffInDays(CarbonImmutable::now());
        } catch (\Throwable) {
            return null;
        }
    }

    public function recalculate(CrmAccount $account): CrmAccount
    {
        $account->score = $this->score($account);

        // Persist an explainable breakdown for verified communities.
        if ($account->type === 'community' && array_key_exists('audience_count', $account->metrics ?? [])) {
            $m = $account->metrics;
            $m['score_breakdown'] = $this->communityFit($m)['breakdown'];
            $m['scored_at'] = CarbonImmutable::now()->toDateString();
            $account->metrics = $m;
        }

        $account->save();

        return $account;
    }
}
