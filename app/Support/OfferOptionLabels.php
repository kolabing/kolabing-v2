<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OfferOption;
use Illuminate\Support\Str;
use Throwable;

/**
 * Slug → human label for the offer taxonomies, server-side.
 *
 * The clients get these from `/lookup/*` and map them themselves; a Blade page has no
 * client, so it reads the same source directly: the admin-managed `offer_options` table,
 * where "food_drink" is already named "Food & Drink". Falling back to
 * `Str::headline()` mirrors {@see OfferOptionValues}, which falls back to hardcoded
 * slugs for the same reason — a missing lookup row must degrade to a slightly worse
 * label, never to a blank chip or an exception.
 *
 * Why labels matter here and not just aesthetically: ROLES §2.3 and §3.3 both require
 * an offer to be shown concretely ("Food & Drink", "Social Media"), never as the
 * abstract word "match".
 */
final class OfferOptionLabels
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $cache = null;

    /**
     * Label a single slug of a given kind.
     */
    public static function get(string $kind, string $slug): string
    {
        $labels = self::all();

        return $labels[$kind][$slug] ?? Str::headline($slug);
    }

    /**
     * Label a list of slugs, dropping empties.
     *
     * @param  array<int, mixed>|null  $slugs
     * @return list<string>
     */
    public static function many(string $kind, ?array $slugs): array
    {
        if ($slugs === null) {
            return [];
        }

        $labels = [];

        foreach ($slugs as $slug) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $labels[] = self::get($kind, $slug);
        }

        return array_values(array_unique($labels));
    }

    /**
     * Forget the memoised table. Tests that seed offer options need this; nothing in
     * request handling does, because the cache lives for one request only.
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            self::$cache = OfferOption::query()
                ->where('is_active', true)
                ->get(['kind', 'slug', 'name'])
                ->groupBy('kind')
                ->map(fn ($rows) => $rows->pluck('name', 'slug')->all())
                ->all();
        } catch (Throwable) {
            // Table missing (pre-migration) or unreachable: headline the slugs.
            self::$cache = [];
        }

        return self::$cache;
    }
}
