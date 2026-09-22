<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Str;

/**
 * Turns the free-text city names that Google Places hands us into the canonical
 * `cities.name` the app filters on, and back again into every spelling that may
 * already sit in the database.
 *
 * Why this exists: a kolab stores its city as a NAME (`kolabs.preferred_city`),
 * not a `cities.id`, and that name came straight from the venue's Google
 * `locality`. Google says "Ciudad de México" (or a borough, "Cuajimalpa de
 * Morelos") where the picker says "Mexico City", so an exact-match filter found
 * nothing. See `config/cities.php` for the alias table.
 */
class CityResolver
{
    /**
     * Normalized name/alias => canonical `cities.name`.
     *
     * @var array<string, string>|null
     */
    private ?array $canonicalByNormalized = null;

    /**
     * Resolve a city from an explicit id, falling back to a free-text name.
     */
    public function resolve(?string $cityId, ?string $cityName): ?City
    {
        if ($cityId !== null && $cityId !== '') {
            $city = City::query()->find($cityId);

            if ($city !== null) {
                return $city;
            }
        }

        return $this->resolveByName($cityName);
    }

    /**
     * Resolve a free-text city name (any known spelling, accents and case
     * ignored) to the `cities` row it means.
     */
    public function resolveByName(?string $cityName): ?City
    {
        $canonical = $this->canonicalFor($cityName);

        if ($canonical === null) {
            return null;
        }

        return City::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($canonical)])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * The canonical `cities.name` for a free-text name, or null when the name
     * matches no known city.
     */
    public function canonicalFor(?string $cityName): ?string
    {
        if ($cityName === null) {
            return null;
        }

        $normalized = self::normalize($cityName);

        if ($normalized === '') {
            return null;
        }

        return $this->lookupTable()[$normalized] ?? null;
    }

    /**
     * The name to STORE for a free-text city: the canonical one when we know the
     * city, otherwise the trimmed input (an unknown city is still worth keeping —
     * it is what the business typed, and `CitySuggestion` may promote it later).
     */
    public function storableName(?string $cityName): ?string
    {
        if ($cityName === null || trim($cityName) === '') {
            return null;
        }

        return $this->canonicalFor($cityName) ?? trim($cityName);
    }

    /**
     * Every spelling a stored city column may hold for this city, for a
     * read-side `whereIn`. Legacy rows written before normalization still carry
     * the Google spelling, so filtering on one name has to match them all.
     *
     * @return array<int, string>
     */
    public function matchingNames(string $cityName): array
    {
        $names = [trim($cityName)];
        $canonical = $this->canonicalFor($cityName);

        if ($canonical !== null) {
            $names[] = $canonical;

            /** @var array<int, string> $aliases */
            $aliases = config('cities.aliases.'.$canonical, []);
            $names = array_merge($names, $aliases);
        }

        $unique = [];

        foreach ($names as $name) {
            $key = mb_strtolower(trim($name));

            if ($key === '') {
                continue;
            }

            $unique[$key] = trim($name);
        }

        return array_values($unique);
    }

    /**
     * Do two free-text city names mean the same city? Falls back to a normalized
     * string comparison when neither is a known city.
     */
    public function sameCity(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        $leftCanonical = $this->canonicalFor($left);
        $rightCanonical = $this->canonicalFor($right);

        if ($leftCanonical !== null && $rightCanonical !== null) {
            return $leftCanonical === $rightCanonical;
        }

        return self::normalize($left) !== '' && self::normalize($left) === self::normalize($right);
    }

    /**
     * Lowercase, transliterate accents away and drop punctuation, so
     * "Ciudad de México", "ciudad de mexico" and "Ciudad De Mexico" are one key.
     */
    public static function normalize(string $value): string
    {
        $ascii = Str::ascii($value);
        $ascii = mb_strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim($ascii);
    }

    /**
     * @return array<string, string>
     */
    private function lookupTable(): array
    {
        if ($this->canonicalByNormalized !== null) {
            return $this->canonicalByNormalized;
        }

        $table = [];

        /** @var array<string, array<int, string>> $aliasMap */
        $aliasMap = config('cities.aliases', []);

        foreach (City::query()->pluck('name') as $name) {
            $table[self::normalize((string) $name)] = (string) $name;
        }

        foreach ($aliasMap as $canonical => $aliases) {
            $table[self::normalize($canonical)] = $canonical;

            foreach ($aliases as $alias) {
                // A real city never loses its own name to another city's alias.
                $key = self::normalize($alias);

                if ($key === '' || isset($table[$key])) {
                    continue;
                }

                $table[$key] = $canonical;
            }
        }

        return $this->canonicalByNormalized = $table;
    }

    /**
     * Drop the memoized lookup table — the cities list changed under us
     * (seeder, admin edit, or a test).
     */
    public function flush(): void
    {
        $this->canonicalByNormalized = null;
    }
}
