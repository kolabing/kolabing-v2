<?php

declare(strict_types=1);

namespace App\Support;

final class ReverbAllowedOrigins
{
    /**
     * Parse the comma-separated allowed origins list used by Reverb.
     *
     * @return array<int, string>
     */
    public static function parse(string $value): array
    {
        $allowedOrigins = array_values(array_filter(array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', $value)
        )));

        return $allowedOrigins === [] ? ['*'] : $allowedOrigins;
    }
}
