<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Single source of truth for the universal `@handle` identity. Handles are
 * stored lowercase, are globally unique across all profiles, and must match
 * `^[a-z0-9_]{3,20}$`. Every write path (onboarding, edit-profile, availability)
 * normalises and validates through here.
 */
class HandleService
{
    public const FORMAT = '/^[a-z0-9_]{3,20}$/';

    /**
     * Normalise a raw handle: strip a leading `@`, trim, lowercase.
     */
    public function normalize(string $handle): string
    {
        return Str::lower(ltrim(trim($handle), '@'));
    }

    /**
     * Whether a normalised handle matches the allowed format.
     */
    public function isValidFormat(string $handle): bool
    {
        return preg_match(self::FORMAT, $handle) === 1;
    }

    /**
     * Whether the (already-normalised) handle is free. $ignoreProfileId lets a
     * profile keep its own current handle on an edit.
     */
    public function isAvailable(string $handle, ?string $ignoreProfileId = null): bool
    {
        return ! Profile::query()
            ->where('handle', $handle)
            ->when($ignoreProfileId !== null, fn ($q) => $q->where('id', '!=', $ignoreProfileId))
            ->exists();
    }

    /**
     * Resolve a profile by its exact handle (any leading `@` is stripped).
     */
    public function resolve(string $handle): ?Profile
    {
        $normalized = $this->normalize($handle);

        if ($normalized === '') {
            return null;
        }

        return Profile::query()->where('handle', $normalized)->first();
    }

    /**
     * Suggest available handles derived from a base string (handle or name) when
     * the requested one is taken. Returns up to $limit free candidates.
     *
     * @return array<int, string>
     */
    public function suggestions(string $base, int $limit = 3): array
    {
        $root = Str::of($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if (strlen($root) < 3) {
            $root = str_pad($root, 3, '0');
        }

        $root = substr($root, 0, 16);

        $candidates = [];
        $attempts = 0;

        while (count($candidates) < $limit && $attempts < 50) {
            $attempts++;
            $suffix = $attempts === 1 ? '' : (string) random_int(1, 9999);
            $candidate = substr($root.$suffix, 0, 20);

            if (! $this->isValidFormat($candidate)) {
                continue;
            }

            if (in_array($candidate, $candidates, true)) {
                continue;
            }

            if ($this->isAvailable($candidate)) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }
}
