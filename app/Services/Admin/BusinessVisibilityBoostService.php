<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\BusinessVisibilityBoostSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read + write the single business_visibility_boost_settings row. Reads
 * cached so DiscoveryOpportunityService (called per discovery request)
 * doesn't hit the DB each time. Cache busted on admin write.
 */
class BusinessVisibilityBoostService
{
    public const CACHE_KEY = 'business_visibility_boost_settings.current';

    /**
     * Return the current settings row (cached). Falls back to the
     * config('gamification_business.visibility_boost_points') defaults if
     * no row exists yet, so discovery scoring never crashes.
     */
    public function current(): BusinessVisibilityBoostSetting
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            fn (): BusinessVisibilityBoostSetting => BusinessVisibilityBoostSetting::query()->first()
                ?? new BusinessVisibilityBoostSetting([
                    'trusted_partner_points' => config('gamification_business.visibility_boost_points.trusted_partner', 5),
                    'community_favourite_points' => config('gamification_business.visibility_boost_points.community_favourite', 10),
                ]),
        );
    }

    /**
     * Update the single row, bust the cache, return the fresh state.
     *
     * @param  array{trusted_partner_points: int, community_favourite_points: int}  $data
     */
    public function update(array $data): BusinessVisibilityBoostSetting
    {
        $row = BusinessVisibilityBoostSetting::query()->first();

        if ($row === null) {
            $row = BusinessVisibilityBoostSetting::query()->create($data);
        } else {
            $row->fill($data);
            $row->save();
        }

        Cache::forget(self::CACHE_KEY);

        return $row->fresh();
    }

    /**
     * Points for a given partner-status tier value ('trusted_partner',
     * 'community_favourite', or anything else -> 0).
     */
    public function pointsForTier(string $tierValue): int
    {
        $settings = $this->current();

        return match ($tierValue) {
            'trusted_partner' => $settings->trusted_partner_points,
            'community_favourite' => $settings->community_favourite_points,
            default => 0,
        };
    }
}
