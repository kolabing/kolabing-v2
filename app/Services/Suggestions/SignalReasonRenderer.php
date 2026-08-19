<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

/**
 * Renders one persisted signal into the reader's locale.
 *
 * `kolab_suggestions.signals` stores keys and raw params, never sentences: the
 * nightly generator runs under the app's default locale, so anything it
 * rendered would reach every reader in that one language. This class is the
 * read-time half of that split, shared by SuggestionResource and the weekly
 * digest — which is why it is a class of its own rather than a scorer method.
 *
 * Slugs are mapped through `suggestions.vocabulary.*` and numbers through
 * Number::format() with the *current* locale, so a stored `cafe` reads "café"
 * in English and "cafetería" in Spanish, and a stored 2.5 reads "2.5" or "2,5".
 *
 * Rows outlive deploys: a signal written last night can name a key this code no
 * longer has, or omit a param a reworded sentence now needs. Both are rendered
 * as an empty string and logged — never as the literal
 * `"suggestions.reason.vibe_fit_great"` or a leaked `:community_type`, which is
 * what the web card and both digest templates would otherwise show. An empty
 * reason is a contract: SuggestionResource drops the signal rather than render
 * a blank line.
 */
class SignalReasonRenderer
{
    /**
     * Param names whose stored value is a measurement rather than a count, and
     * so renders with one decimal place in every locale.
     */
    private const DECIMAL_PARAMS = ['km', 'rating'];

    /**
     * Param names whose stored value is a matrix slug; each doubles as its
     * `suggestions.vocabulary.*` group.
     */
    private const VOCABULARY_PARAMS = ['community_type', 'business_category'];

    /**
     * @param  array<string, mixed>  $signal  one entry of kolab_suggestions.signals
     * @return array{label: string, reason: string}
     */
    public function render(array $signal): array
    {
        $key = is_string($signal['key'] ?? null) ? $signal['key'] : '';
        $reasonKey = is_string($signal['reason_key'] ?? null) ? $signal['reason_key'] : '';
        $params = is_array($signal['reason_params'] ?? null) ? $signal['reason_params'] : [];

        return [
            'label' => $this->label($key),
            'reason' => $this->reason($reasonKey, $params),
        ];
    }

    private function label(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $langKey = 'suggestions.signal.'.$key;

        if (! Lang::has($langKey)) {
            Log::warning('Persisted suggestion signal names a key this code no longer has.', [
                'signal_key' => $key,
                'lang_key' => $langKey,
            ]);

            return '';
        }

        return (string) __($langKey);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function reason(string $reasonKey, array $params): string
    {
        if ($reasonKey === '') {
            return '';
        }

        $langKey = 'suggestions.reason.'.$reasonKey;

        if (! Lang::has($langKey)) {
            Log::warning('Persisted suggestion reason names a key this code no longer has.', [
                'reason_key' => $reasonKey,
                'lang_key' => $langKey,
            ]);

            return '';
        }

        $rendered = $this->renderParams($params);
        $missing = $this->missingPlaceholders($langKey, $rendered);

        if ($missing !== []) {
            Log::warning('Persisted suggestion reason is missing params its sentence needs.', [
                'reason_key' => $reasonKey,
                'missing_params' => $missing,
            ]);

            return '';
        }

        return (string) __($langKey, $rendered);
    }

    /**
     * Placeholders the *template* declares but the persisted params do not carry.
     * Comparing against the template rather than scanning the rendered sentence
     * keeps a legitimate colon in the copy from reading as a leaked placeholder.
     *
     * @param  array<string, string>  $params
     * @return array<int, string>
     */
    private function missingPlaceholders(string $langKey, array $params): array
    {
        $template = __($langKey);

        if (! is_string($template)) {
            return [];
        }

        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $template, $matches);

        $required = array_unique(array_map(
            static fn (string $placeholder): string => mb_strtolower($placeholder),
            $matches[1]
        ));

        return array_values(array_diff($required, array_keys($params)));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function renderParams(array $params): array
    {
        $locale = app()->getLocale();
        $rendered = [];

        foreach ($params as $name => $value) {
            $rendered[(string) $name] = match (true) {
                in_array($name, self::VOCABULARY_PARAMS, true) => $this->vocabulary((string) $name, (string) $value),
                is_array($value) => $this->itemList($value),
                in_array($name, self::DECIMAL_PARAMS, true) => Number::format((float) $value, 1, locale: $locale),
                is_int($value) || is_float($value) => (string) Number::format((float) $value, locale: $locale),
                default => (string) $value,
            };
        }

        return $rendered;
    }

    /**
     * Offer/need slugs come from the DB-driven `offer_options` vocabulary (~50
     * values), so labelling them belongs to the database rather than a lang
     * file; de-underscoring is all the rendering they get here.
     *
     * @param  array<array-key, mixed>  $items
     */
    private function itemList(array $items): string
    {
        return implode(', ', array_map(
            static fn (mixed $item): string => str_replace('_', ' ', (string) $item),
            array_values($items)
        ));
    }

    /**
     * Human label for a matrix slug, falling back to the de-underscored slug so
     * a matrix that grows a column can never render an empty reason line.
     */
    private function vocabulary(string $group, string $value): string
    {
        $key = 'suggestions.vocabulary.'.$group.'.'.$value;

        return Lang::has($key)
            ? (string) __($key)
            : str_replace('_', ' ', $value);
    }
}
