<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Models\CommunityType;
use Illuminate\Support\Facades\Schema;

/**
 * Single source for the canonical 17-slug community-type vocabulary.
 *
 * Two stores hold the vocabulary today and they diverge on separators:
 *  - the admin-managed `community_types` table seeds hyphenated slugs (`run-club`),
 *  - the legacy `CommunityOnboardingRequest::COMMUNITY_TYPES` constant (and the
 *    `communities.type` column that inherits from it) use underscores (`run_club`).
 *
 * Validation accepts either form; matching expands a slug to both separators so a
 * `?type=run_club` filter still hits a community row stored as `run-club` (or vice
 * versa). This is NEVER the 5-value App\Enums\CommunityType placeholder.
 */
final class CommunityTypeVocabulary
{
    /**
     * Every slug accepted for validation: the union of the seeded
     * `community_types` table and the COMMUNITY_TYPES constant, plus the
     * hyphen/underscore variant of each so both separator styles validate.
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        $slugs = CommunityOnboardingRequest::COMMUNITY_TYPES;

        if (Schema::hasTable('community_types')) {
            $tableSlugs = CommunityType::query()->pluck('slug')->all();
            $slugs = array_merge($slugs, $tableSlugs);
        }

        $expanded = [];
        foreach ($slugs as $slug) {
            foreach (self::variants((string) $slug) as $variant) {
                $expanded[] = $variant;
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    /**
     * The hyphen and underscore variants of a slug (deduplicated), used to match
     * a filter value against a stored `communities.type` regardless of separator.
     *
     * @return array<int, string>
     */
    public static function variants(string $slug): array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return [];
        }

        return array_values(array_unique([
            $slug,
            str_replace('_', '-', $slug),
            str_replace('-', '_', $slug),
        ]));
    }
}
