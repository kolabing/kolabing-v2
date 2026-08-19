<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Services\Suggestions\SignalReasonRenderer;
use App\Support\Matching\CategoryFitMatrix;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class SignalReasonRendererTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signal(array $overrides = []): array
    {
        return array_merge([
            'key' => 'category_fit',
            'reason_key' => 'category_fit',
            'reason_params' => [
                'community_type' => 'food_community',
                'business_category' => 'cafe',
            ],
            'weight' => 0.25,
            'score' => 1.0,
        ], $overrides);
    }

    public function test_it_renders_the_label_and_the_reason(): void
    {
        $rendered = (new SignalReasonRenderer)->render($this->signal());

        $this->assertSame(__('suggestions.signal.category_fit'), $rendered['label']);
        $this->assertStringContainsString('café', mb_strtolower($rendered['reason']));
    }

    public function test_it_localises_both_sides_of_the_interpolation(): void
    {
        App::setLocale('es');

        $rendered = (new SignalReasonRenderer)->render($this->signal());

        $this->assertSame('Afinidad de categoría', $rendered['label']);
        $this->assertStringContainsString('gastronomía', $rendered['reason']);
        $this->assertStringContainsString('cafetería', $rendered['reason']);
    }

    public function test_it_formats_numbers_in_the_readers_locale(): void
    {
        $renderer = new SignalReasonRenderer;
        $signal = $this->signal([
            'key' => 'location_fit',
            'reason_key' => 'location_distance',
            'reason_params' => ['km' => 2.5],
        ]);

        $this->assertStringContainsString('2.5', $renderer->render($signal)['reason']);

        App::setLocale('es');

        $this->assertStringContainsString('2,5', $renderer->render($signal)['reason']);
    }

    public function test_it_renders_a_whole_rating_with_one_decimal_place(): void
    {
        $rendered = (new SignalReasonRenderer)->render($this->signal([
            'key' => 'delivery_proof',
            'reason_key' => 'delivery_proof_business',
            'reason_params' => ['reviews' => 4, 'rating' => 5.0],
        ]));

        $this->assertStringContainsString('5.0', $rendered['reason']);
        $this->assertStringContainsString('4', $rendered['reason']);
    }

    public function test_it_joins_offer_need_slugs_into_a_readable_list(): void
    {
        $rendered = (new SignalReasonRenderer)->render($this->signal([
            'key' => 'offer_need_fit',
            'reason_key' => 'offer_need_overlap',
            'reason_params' => ['items' => ['venue', 'food_drink']],
        ]));

        $this->assertStringContainsString('venue, food drink', $rendered['reason']);
    }

    /**
     * The vocabulary map covers every matrix key by construction (asserted
     * below), so this fallback is unreachable through generated data — but it is
     * what keeps a future matrix column from rendering an empty reason line.
     */
    public function test_an_unmapped_slug_degrades_to_the_de_underscored_slug(): void
    {
        $rendered = (new SignalReasonRenderer)->render($this->signal([
            'reason_params' => [
                'community_type' => 'board_game_community',
                'business_category' => 'board_game_cafe',
            ],
        ]));

        $this->assertStringContainsString('board game community', $rendered['reason']);
        $this->assertStringContainsString('board game cafe', $rendered['reason']);
    }

    public function test_it_renders_nothing_rather_than_a_dotted_key_for_a_signal_without_keys(): void
    {
        $rendered = (new SignalReasonRenderer)->render(['weight' => 0.25, 'score' => 1.0]);

        $this->assertSame(['label' => '', 'reason' => ''], $rendered);
    }

    /**
     * The vocabulary map covers every matrix key by construction, which is what
     * makes the slug fallback above unreachable through real data. Assert the
     * invariant rather than relying on the fallback: this fails the moment a
     * matrix column is added without a translation, which is the only way that
     * fallback could ever fire in production.
     */
    public function test_every_matrix_key_has_a_vocabulary_entry_in_every_locale(): void
    {
        $communityTypes = array_keys(CategoryFitMatrix::MATRIX);
        $businessCategories = array_keys(array_merge(...array_values(CategoryFitMatrix::MATRIX)));

        $this->assertNotEmpty($communityTypes);
        $this->assertNotEmpty($businessCategories);

        foreach (['en', 'es', 'ca'] as $locale) {
            foreach ($communityTypes as $communityType) {
                $key = 'suggestions.vocabulary.community_type.'.$communityType;

                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "Missing translation [{$key}] for locale [{$locale}]."
                );
            }

            foreach ($businessCategories as $businessCategory) {
                $key = 'suggestions.vocabulary.business_category.'.$businessCategory;

                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "Missing translation [{$key}] for locale [{$locale}]."
                );
            }
        }
    }

    /**
     * Every reason key the scorer can emit must exist in all three locales, or a
     * Spanish reader gets a dotted lang path where a sentence belongs.
     */
    public function test_every_reason_and_signal_key_exists_in_every_locale(): void
    {
        $keys = array_keys(require lang_path('en/suggestions.php'));

        $this->assertSame(['signal', 'reason', 'vocabulary'], $keys);

        foreach (['signal', 'reason'] as $group) {
            $groupKeys = array_keys((require lang_path('en/suggestions.php'))[$group]);

            $this->assertNotEmpty($groupKeys);

            foreach (['en', 'es', 'ca'] as $locale) {
                foreach ($groupKeys as $groupKey) {
                    $key = 'suggestions.'.$group.'.'.$groupKey;

                    $this->assertTrue(
                        Lang::has($key, $locale, false),
                        "Missing translation [{$key}] for locale [{$locale}]."
                    );
                }
            }
        }
    }
}
