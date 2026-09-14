<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

/**
 * The Google Maps import card (admin.users._places-import) writes `primary_venue` into
 * its hidden input as `JSON.stringify(...)`, since a plain HTML input can only ever hold
 * a string. Every request class that accepts that field must decode it back to an array
 * before the `array` validation rule runs against it, or every real browser submission
 * that used the import fails validation -- caught live 2026-09-14 (Daniel: "the quick add
 * which has the google maps part still doesn't work"), where `validator(['primary_venue'
 * => '{"formatted_address":"x"}'], ['primary_venue' => ['array']])` failed 100% of the
 * time because a JSON string is not a PHP array.
 */
trait DecodesPrimaryVenue
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('primary_venue'))) {
            $decoded = json_decode((string) $this->input('primary_venue'), true);

            $this->merge([
                'primary_venue' => (is_array($decoded) && $decoded !== []) ? $decoded : null,
            ]);
        }
    }
}
