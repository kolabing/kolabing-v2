<?php

declare(strict_types=1);

namespace App\Support\Matching;

/**
 * Which offer slugs mean the same thing across the three offer taxonomies.
 *
 * `OfferOption` keeps a separate vocabulary per `kind`, and a business gives out
 * of `offering` while a community asks out of `need`. The two lists agree on
 * `venue`, `food_drink`, `discount`, `products` and `other`, and disagree on the
 * rest: `offering` has `venue_space`, `free_drinks` and `sponsorship` where
 * `need` has `venue`, `food_drink` and `sponsor`. A raw `array_intersect` across
 * the two therefore reports "no overlap" for a business offering exactly what
 * the community asked for — a false 0.0, not a missing signal.
 *
 * Extracted from DiscoveryOpportunityService::OFFER_TYPE_ALIASES so the
 * suggestion engine and, later, Explore can share one table. Two deliberate
 * differences from that private copy:
 *
 * - `sponsorship` is folded onto `sponsor`. Explore's map lists `sponsorship`
 *   with itself as its only alias, which does not bridge the two vocabularies
 *   at all; bridging it is the entire reason this table exists here.
 * - Explore is *not* repointed at this class in this change. Its suite is the
 *   evidence that its behaviour was left alone; adopting this table there is a
 *   ranking change and belongs in its own commit.
 *
 * This table is for **comparison only**. The slugs that reach
 * `suggested_format.offer` and `expects` stay in the vocabulary the Kolab form
 * validates against, which is why `intersect()` returns the caller's own
 * spellings rather than the canonical ones.
 */
final class OfferTypeAliases
{
    /**
     * Canonical slug => every stored spelling that means it, canonical included.
     *
     * A few spellings come from adjacent `OfferOption` kinds rather than the
     * three offer taxonomies — `discount_code` is a `product_interaction` slug
     * and `free_samples` a `kolab_highlight` one — because Explore text-matches
     * offer terms across fields that draw on those kinds. They are inert for the
     * offer/need comparison here and are kept so that adopting this table in
     * Explore later does not quietly narrow its matching. `commission` is in no
     * taxonomy at all and is likewise inherited rather than invented.
     *
     * @var array<string, array<int, string>>
     */
    public const ALIASES = [
        'venue' => ['venue', 'venue_space'],
        'food_drink' => ['food_drink', 'free_drinks'],
        'discount' => ['discount', 'discount_code'],
        'products' => ['products', 'free_samples'],
        'sponsor' => ['sponsor', 'sponsorship'],
        'social_media' => ['social_media'],
        'content_creation' => ['content_creation'],
        'other' => ['other', 'commission'],
    ];

    /**
     * Canonical slugs that carry no information about fit. `other` is a real
     * option in both the `offering` and `need` taxonomies, but an `other`↔`other`
     * match says only that both sides declined to say what they meant: counted as
     * coverage it inflates `offer_need_fit`, and carried into
     * `suggested_format` it pre-fills a Kolab asking for `other`. Excluded from
     * matching and from the coverage denominator alike.
     *
     * @var array<int, string>
     */
    public const UNINFORMATIVE = ['other'];

    /**
     * The slug's canonical form, or the slug itself when it is not in the table.
     * An unknown slug is its own canonical form rather than an error: both
     * taxonomies are admin-editable at runtime, so a new option must compare
     * against itself rather than vanish.
     */
    public static function canonical(string $slug): string
    {
        $normalized = OfferVocabulary::slugs([$slug])[0] ?? '';

        foreach (self::ALIASES as $canonical => $spellings) {
            if (in_array($normalized, $spellings, true)) {
                return $canonical;
            }
        }

        return $normalized;
    }

    /**
     * The entries of `$keep` that mean something in `$against`, compared on
     * canonical form but returned in `$keep`'s own spelling — because the result
     * pre-fills a Kolab field validated against `$keep`'s taxonomy.
     *
     * Deduplicated by canonical form, first spelling winning: a business that
     * declares both `venue` and `venue_space` offers one thing, and listing it
     * twice would both inflate the coverage ratio and read as two offers in the
     * reason copy. `UNINFORMATIVE` slugs never match at all — the `$against` set
     * excludes them, so nothing on the `$keep` side can pair with one.
     *
     * @param  array<int, string>  $keep
     * @param  array<int, string>  $against
     * @return array<int, string>
     */
    public static function intersect(array $keep, array $against): array
    {
        $wanted = self::canonicalSet($against);

        $matched = [];

        foreach ($keep as $slug) {
            $canonical = self::canonical($slug);

            if ($canonical === '' || ! isset($wanted[$canonical]) || isset($matched[$canonical])) {
                continue;
            }

            $matched[$canonical] = $slug;
        }

        return array_values($matched);
    }

    /**
     * The distinct *informative* canonical slugs in a list — the honest
     * denominator for "how much of what they asked for do you cover", since two
     * spellings of one ask are one ask and `other` is not an ask at all.
     *
     * @param  array<int, string>  $slugs
     * @return array<string, true>
     */
    public static function canonicalSet(array $slugs): array
    {
        $set = [];

        foreach ($slugs as $slug) {
            $canonical = self::canonical($slug);

            if ($canonical !== '' && ! in_array($canonical, self::UNINFORMATIVE, true)) {
                $set[$canonical] = true;
            }
        }

        return $set;
    }
}
