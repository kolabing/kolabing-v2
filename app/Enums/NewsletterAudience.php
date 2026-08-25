<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side of the marketplace a landing-page mailing-list signup identified
 * with. Mirrors the two paid/served audiences on the site (communities and
 * businesses); a signup may omit it.
 */
enum NewsletterAudience: string
{
    case Community = 'community';
    case Business = 'business';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
