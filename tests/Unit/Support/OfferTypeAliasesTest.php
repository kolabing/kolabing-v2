<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Matching\OfferTypeAliases;
use Tests\TestCase;

class OfferTypeAliasesTest extends TestCase
{
    public function test_it_folds_the_offering_spellings_onto_the_need_vocabulary(): void
    {
        $this->assertSame('venue', OfferTypeAliases::canonical('venue_space'));
        $this->assertSame('food_drink', OfferTypeAliases::canonical('free_drinks'));
        $this->assertSame('sponsor', OfferTypeAliases::canonical('sponsorship'));
        $this->assertSame('discount', OfferTypeAliases::canonical('discount_code'));
        $this->assertSame('products', OfferTypeAliases::canonical('free_samples'));
    }

    /**
     * Both taxonomies are admin-editable at runtime, so an option added after
     * this table was written has to compare against itself rather than vanish
     * into an empty canonical form.
     */
    public function test_an_unknown_slug_is_its_own_canonical_form(): void
    {
        $this->assertSame('ugc_content', OfferTypeAliases::canonical('UGC_Content'));
    }

    /**
     * The whole reason this table was extracted: a plain intersection of the two
     * vocabularies reports no overlap for a business offering exactly what the
     * community asked for.
     */
    public function test_it_matches_across_vocabularies_but_returns_the_callers_spelling(): void
    {
        $this->assertSame(
            ['venue_space', 'sponsorship'],
            OfferTypeAliases::intersect(['venue_space', 'sponsorship', 'social_media'], ['venue', 'sponsor'])
        );
    }

    public function test_it_keeps_one_entry_per_canonical_slug(): void
    {
        $this->assertSame(
            ['venue'],
            OfferTypeAliases::intersect(['venue', 'venue_space'], ['venue'])
        );
    }

    public function test_nothing_in_common_is_an_empty_intersection(): void
    {
        $this->assertSame([], OfferTypeAliases::intersect(['venue'], ['discount', 'sponsor']));
        $this->assertSame([], OfferTypeAliases::intersect([], ['venue']));
        $this->assertSame([], OfferTypeAliases::intersect(['venue'], []));
    }

    /**
     * `other` is a live option in both taxonomies, but it carries no information
     * about fit — so it can neither match nor count toward coverage.
     */
    public function test_an_uninformative_slug_never_matches(): void
    {
        $this->assertSame([], OfferTypeAliases::intersect(['other'], ['other']));
        $this->assertSame([], array_keys(OfferTypeAliases::canonicalSet(['other'])));
    }

    public function test_an_uninformative_slug_does_not_block_the_real_ones(): void
    {
        $this->assertSame(
            ['venue'],
            OfferTypeAliases::intersect(['venue', 'other'], ['other', 'venue'])
        );
    }

    public function test_the_canonical_set_counts_distinct_asks_not_spellings(): void
    {
        $this->assertSame(
            ['venue', 'food_drink'],
            array_keys(OfferTypeAliases::canonicalSet(['venue', 'venue_space', 'free_drinks', 'food_drink']))
        );
    }
}
