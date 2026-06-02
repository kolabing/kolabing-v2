<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\GamificationBadgeSlug;
use App\Models\GamificationBadgeOverride;
use Illuminate\Support\Facades\Cache;

class GamificationBadgeMetadataService
{
    private const CACHE_KEY = 'admin.gamification.badge_overrides';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Resolved display metadata for a single slug. DB override wins; enum
     * methods are the fallback so partial overrides are safe.
     *
     * @return array{name: string, description: string, icon: ?string, audiences: array<int, string>}
     */
    public function displayFor(GamificationBadgeSlug $slug): array
    {
        $override = $this->overrides()[$slug->value] ?? null;

        return [
            'name' => $override?->name ?: $slug->displayName(),
            'description' => $override?->description ?: $slug->description(),
            'icon' => $override?->icon,
            'audiences' => $override?->audiences ?: $slug->audiences(),
        ];
    }

    /**
     * Upsert the override row for a slug. Empty / whitespace strings are
     * stored as null so the resolver falls back to the enum default.
     *
     * @param  array{name?: ?string, description?: ?string, icon?: ?string, audiences?: array<int, string>|null}  $data
     */
    public function update(GamificationBadgeSlug $slug, array $data): GamificationBadgeOverride
    {
        $row = GamificationBadgeOverride::query()->updateOrCreate(
            ['badge_slug' => $slug->value],
            [
                'name' => $this->trimOrNull($data['name'] ?? null),
                'description' => $this->trimOrNull($data['description'] ?? null),
                'icon' => $this->trimOrNull($data['icon'] ?? null),
                'audiences' => isset($data['audiences']) && $data['audiences'] !== []
                    ? array_values(array_unique($data['audiences']))
                    : null,
            ],
        );

        Cache::forget(self::CACHE_KEY);

        return $row;
    }

    /**
     * @return array<string, GamificationBadgeOverride> Keyed by badge_slug value.
     */
    private function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, static function (): array {
            return GamificationBadgeOverride::query()
                ->get()
                ->keyBy(fn (GamificationBadgeOverride $row): string => $row->badge_slug->value)
                ->all();
        });
    }

    private function trimOrNull(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }
}
