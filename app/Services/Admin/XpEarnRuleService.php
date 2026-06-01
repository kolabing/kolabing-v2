<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\PointEventType;
use App\Models\XpEarnRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Authoritative read + write path for per-action XP awards. Both
 * GamificationWalletService::awardPoints() and the /gamification/config
 * endpoint consult this service, so a single admin save changes both the
 * ledger write AND the value the app renders.
 */
class XpEarnRuleService
{
    public const CACHE_KEY = 'gamification.config';

    /**
     * Points granted for a given PointEventType. Reads the active rule row;
     * falls back to PointEventType::defaultPoints() if the row is missing or
     * deactivated, so the award path never crashes mid-deploy.
     */
    public function pointsFor(PointEventType $event): int
    {
        $rule = XpEarnRule::query()
            ->where('event_type', $event->value)
            ->where('is_active', true)
            ->first();

        return $rule?->points ?? $event->defaultPoints();
    }

    /**
     * All rules ordered by `position` for admin list rendering.
     *
     * @return Collection<int, XpEarnRule>
     */
    public function ordered(): Collection
    {
        return XpEarnRule::query()
            ->orderBy('position')
            ->orderBy('event_type')
            ->get();
    }

    /**
     * Update one rule and bust the config cache so the next request rebuilds.
     *
     * @param  array{points?: int, label?: string, is_active?: bool, position?: int}  $data
     */
    public function update(XpEarnRule $rule, array $data): XpEarnRule
    {
        $rule->fill($data);
        $rule->save();

        Cache::forget(self::CACHE_KEY);

        return $rule->fresh();
    }
}
