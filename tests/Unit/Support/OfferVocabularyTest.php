<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Matching\OfferVocabulary;
use Tests\TestCase;

class OfferVocabularyTest extends TestCase
{
    public function test_a_list_of_slugs_passes_through(): void
    {
        $this->assertSame(['venue', 'food_drink'], OfferVocabulary::slugs(['venue', 'food_drink']));
    }

    /**
     * Copied verbatim from a live `kolabs.offering` row (read-only, 2026-08-19).
     * Roughly 45% of rows in these four columns are objects rather than lists,
     * while the request validators only accept lists — so a reader that handles
     * one shape silently loses half the corpus, and `offer_need_fit` reports a
     * false 0.0 for it rather than dropping out.
     */
    public function test_the_legacy_keyed_boolean_shape_yields_only_the_enabled_keys(): void
    {
        $stored = [
            'venue' => true,
            'food_drink' => false,
            'social_media_exposure' => false,
            'content_creation' => false,
            'discount' => ['enabled' => false],
        ];

        $this->assertSame(['venue'], OfferVocabulary::slugs($stored));
    }

    /**
     * A discount carries a percentage alongside its flag, so it nests rather than
     * being a bare boolean. Reading the nesting as "present, therefore true"
     * would turn every switched-off discount into a declared offer.
     */
    public function test_a_nested_enabled_flag_is_read_on_both_sides(): void
    {
        $this->assertSame(
            ['discount'],
            OfferVocabulary::slugs(['discount' => ['enabled' => true, 'percentage' => 20]])
        );

        $this->assertSame(
            [],
            OfferVocabulary::slugs(['discount' => ['percentage' => 20]])
        );
    }

    public function test_truthy_scalars_other_than_a_boolean_count_as_enabled(): void
    {
        $this->assertSame(['venue', 'products'], OfferVocabulary::slugs([
            'venue' => 1,
            'food_drink' => 0,
            'products' => 'true',
        ]));
    }

    public function test_values_are_trimmed_lowercased_and_deduplicated(): void
    {
        $this->assertSame(['venue'], OfferVocabulary::slugs([' Venue ', 'venue']));
    }

    public function test_anything_that_is_not_an_array_is_no_declared_offer(): void
    {
        $this->assertSame([], OfferVocabulary::slugs(null));
        $this->assertSame([], OfferVocabulary::slugs('venue'));
        $this->assertSame([], OfferVocabulary::slugs([]));
        $this->assertSame([], OfferVocabulary::slugs(['', '   ']));
    }
}
