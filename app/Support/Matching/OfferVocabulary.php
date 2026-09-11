<?php

declare(strict_types=1);

namespace App\Support\Matching;

/**
 * Reads a stored Kolab offer/need column into a plain list of slugs.
 *
 * `kolabs.needs`, `offers_in_return`, `offering` and `expects` are jsonb, and
 * production holds **two incompatible shapes in the same columns** — verified
 * read-only on 2026-08-19: `needs` 8 object / 19 array, `offers_in_return`
 * 22 / 19, `offering` 27 / 26, `expects` 27 / 17. Roughly 45% of rows are the
 * legacy keyed-boolean form:
 *
 *     {"venue":true,"food_drink":false,"social_media_exposure":false,
 *      "content_creation":false,"discount":{"enabled":false}}
 *
 * while the request validators only ever accept a list of slugs. An
 * `array_intersect` against an object row matches nothing, so `offer_need_fit`
 * would return a *false 0.0* — "no overlap between what you offer and what they
 * need" — for half the corpus. That is worse than dropping the signal: 0.0
 * scores the pair as an actively bad match, where a missing signal would have
 * been renormalised away and reported as lower confidence.
 *
 * Lives beside CategoryFitMatrix, and for the same reason: every surface that
 * reads these columns has to agree about what a stored row means, and the
 * agreement cannot live in one caller.
 */
final class OfferVocabulary
{
    /**
     * A list row passes through; an object row becomes the keys that are
     * actually switched on.
     *
     * `true` may be spelled as a boolean, as `1`, or as a nested
     * `{"enabled": true}` — the `discount` entry in the example above nests,
     * because a discount carries a percentage alongside its flag. Anything else,
     * including `{"enabled": false}`, is not a declared offer.
     *
     * @return array<int, string>
     */
    public static function slugs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $slugs = [];

        foreach ($value as $key => $entry) {
            if (is_int($key)) {
                if (is_string($entry) && trim($entry) !== '') {
                    $slugs[] = self::normalize($entry);
                }

                continue;
            }

            if (is_string($key) && self::isEnabled($entry)) {
                $slugs[] = self::normalize($key);
            }
        }

        return array_values(array_unique(array_filter($slugs, static fn (string $slug): bool => $slug !== '')));
    }

    private static function isEnabled(mixed $entry): bool
    {
        if (is_array($entry)) {
            return array_key_exists('enabled', $entry) && self::isEnabled($entry['enabled']);
        }

        return filter_var($entry, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
