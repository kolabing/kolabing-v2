<?php

declare(strict_types=1);

use App\Models\BusinessType;
use App\Models\CommunityType;
use Illuminate\Database\Migrations\Migration;

/**
 * Makes the *_types tables the single source of truth (DECISION 2026-06-10),
 * normalised to the EXACT underscore vocabulary that communities.type +
 * /lookup already use (CommunityOnboardingRequest::COMMUNITY_TYPES /
 * BusinessOnboardingRequest::BUSINESS_TYPES). The legacy hyphen slugs in the
 * tables are migrated in place (run-club → run_club) so the slug matches the
 * stored community values — enabling exists:*_types,slug validation.
 *
 * Defensive: each canonical row is matched by name OR by any existing slug
 * variant (hyphen/underscore) and UPDATED in place; created only if absent.
 * Never deletes. Idempotent.
 */
return new class extends Migration
{
    /** [name, canonical underscore slug, bundled-SVG icon key] */
    private const COMMUNITIES = [
        ['Run Club', 'run_club', 'running'],
        ['Fitness Community', 'fitness_community', 'dumbbell'],
        ['Wellness Community', 'wellness_community', 'heart'],
        ['Art & Creative Community', 'art_creative_community', 'palette'],
        ['Photography Community', 'photography_community', 'camera'],
        ['Music Community', 'music_community', 'music'],
        ['Dance Community', 'dance_community', 'music-2'],
        ['Tech / Startup Community', 'tech_startup_community', 'laptop'],
        ['Book Club', 'book_club', 'book'],
        ['Sustainability Community', 'sustainability_community', 'leaf'],
        ['Food Community', 'food_community', 'utensils'],
        ['Travel Community', 'travel_community', 'plane'],
        ['Student Community', 'student_community', 'graduation-cap'],
        ['Professional Networking Community', 'professional_networking_community', 'briefcase'],
        ['Business / Coworking Community', 'business_coworking', 'users'],
        ['Hobby Community', 'hobby_community', 'star'],
        ['Other', 'other', 'ellipsis'],
    ];

    /** [name, slug] — business slug == app-bundled SVG key, so icon = slug */
    private const BUSINESSES = [
        ['Cafe', 'cafe'],
        ['Restaurant', 'restaurant'],
        ['Bar', 'bar'],
        ['Bakery', 'bakery'],
        ['Coworking Space', 'coworking'],
        ['Gym & Fitness', 'gym'],
        ['Salon & Spa', 'salon'],
        ['Retail Store', 'retail'],
        ['Hotel & Accommodation', 'hotel'],
        ['Other', 'other'],
    ];

    public function up(): void
    {
        foreach (self::COMMUNITIES as $i => [$name, $slug, $icon]) {
            $this->upsert(CommunityType::query(), $name, $slug, $icon, $i + 1);
        }
        foreach (self::BUSINESSES as $i => [$name, $slug]) {
            $this->upsert(BusinessType::query(), $name, $slug, $slug, $i + 1);
        }
    }

    /**
     * Match an existing row by canonical slug, hyphen variant, or name; update
     * it in place to the canonical values; create only if none exists.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function upsert($query, string $name, string $slug, string $icon, int $sort): void
    {
        $hyphen = str_replace('_', '-', $slug);

        $row = (clone $query)->where('slug', $slug)
            ->orWhere('slug', $hyphen)
            ->orWhere('name', $name)
            ->first();

        $attrs = ['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sort, 'is_active' => true];

        if ($row !== null) {
            $row->fill($attrs)->save();
        } else {
            (clone $query)->create($attrs);
        }
    }

    public function down(): void
    {
        // No-op (data normalisation; nothing destructive to roll back).
    }
};
