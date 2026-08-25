<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much evidence stands behind a suggestion's score. Stored on
 * `kolab_suggestions.confidence`, derived by the scorer from the share of
 * signals that had real data (thresholds in `config/suggestions.php`).
 * Read by the card, the digest templates and the i18n keys, so it is an
 * enum rather than a string to keep a typo from reaching all three.
 */
enum SuggestionConfidence: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
