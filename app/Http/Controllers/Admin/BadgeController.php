<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GamificationBadgeSlug;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBadgeOverrideRequest;
use App\Http\Requests\Admin\UpdateBadgeRequest;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\EarnedBadge;
use App\Models\GamificationBadgeOverride;
use App\Services\Admin\GamificationBadgeMetadataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Unified admin view over both badge systems (Q4 + PR-7):
 *
 * - System A: rows in `badges` (attendee-flavoured milestones, DB-editable).
 *   Awarded via `badge_awards`.
 * - System B: GamificationBadgeSlug enum (business + community oriented).
 *   The slug identity stays in the enum; admin can override the displayed
 *   name / description / icon / audiences via `gamification_badge_overrides`
 *   so copy changes don't need a deploy. Awarded via `earned_badges`.
 */
class BadgeController extends Controller
{
    public function __construct(
        private readonly GamificationBadgeMetadataService $metadata,
    ) {}

    public function index(): View
    {
        return view('admin.gamification.badges.index', [
            'attendeeBadges' => $this->attendeeBadges(),
            'businessBadges' => $this->systemBBadges('business'),
            'communityBadges' => $this->systemBBadges('community'),
        ]);
    }

    public function edit(Badge $badge): View
    {
        return view('admin.gamification.badges.edit', [
            'badge' => $badge,
        ]);
    }

    public function update(UpdateBadgeRequest $request, Badge $badge): RedirectResponse
    {
        $badge->update($request->validated());

        return redirect()
            ->route('admin.gamification.badges.index')
            ->with('status', "Badge \"{$badge->name}\" updated.");
    }

    public function editSystemB(string $slug): View
    {
        $enumSlug = GamificationBadgeSlug::tryFrom($slug) ?? abort(404);
        $override = GamificationBadgeOverride::query()
            ->where('badge_slug', $enumSlug->value)
            ->first();

        return view('admin.gamification.badges.edit-system-b', [
            'slug' => $enumSlug,
            'override' => $override,
            'defaults' => [
                'name' => $enumSlug->displayName(),
                'description' => $enumSlug->description(),
                'audiences' => $enumSlug->audiences(),
            ],
        ]);
    }

    public function updateSystemB(UpdateBadgeOverrideRequest $request, string $slug): RedirectResponse
    {
        $enumSlug = GamificationBadgeSlug::tryFrom($slug) ?? abort(404);
        $this->metadata->update($enumSlug, [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'icon' => $request->validated('icon'),
            'audiences' => $request->validated('audiences'),
        ]);

        $resolvedName = $this->metadata->displayFor($enumSlug)['name'];

        return redirect()
            ->route('admin.gamification.badges.index')
            ->with('status', "Badge \"{$resolvedName}\" overrides saved.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attendeeBadges(): array
    {
        $awardsByBadge = BadgeAward::query()
            ->select('badge_id', DB::raw('COUNT(*) as award_count'))
            ->groupBy('badge_id')
            ->pluck('award_count', 'badge_id')
            ->all();

        return Badge::query()
            ->orderBy('milestone_value')
            ->get()
            ->map(fn (Badge $badge): array => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'milestone_type' => $badge->milestone_type->value,
                'milestone_value' => $badge->milestone_value,
                'award_count' => (int) ($awardsByBadge[$badge->id] ?? 0),
                'editable' => true,
            ])
            ->all();
    }

    /**
     * Build the System B (enum-backed) badge view, scoped to one audience.
     * Display strings + audiences pass through the resolver so admin
     * overrides win over enum defaults. Award counts are joined against
     * `earned_badges` × `profiles` and filtered by `profiles.user_type`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function systemBBadges(string $audience): array
    {
        $userType = UserType::tryFrom($audience);
        $counts = [];

        if ($userType !== null) {
            $counts = EarnedBadge::query()
                ->join('profiles', 'profiles.id', '=', 'earned_badges.profile_id')
                ->where('profiles.user_type', $userType->value)
                ->select('earned_badges.badge_slug', DB::raw('COUNT(*) as award_count'))
                ->groupBy('earned_badges.badge_slug')
                ->pluck('award_count', 'badge_slug')
                ->all();
        }

        $rows = [];
        foreach (GamificationBadgeSlug::cases() as $slug) {
            $resolved = $this->metadata->displayFor($slug);
            if (! in_array($audience, $resolved['audiences'], true)) {
                continue;
            }
            $rows[] = [
                'slug' => $slug->value,
                'name' => $resolved['name'],
                'description' => $resolved['description'],
                'icon' => $resolved['icon'],
                'award_count' => (int) ($counts[$slug->value] ?? 0),
                'editable' => true,
                'edit_route' => route('admin.gamification.badges.system-b.edit', $slug->value),
            ];
        }

        return $rows;
    }
}
