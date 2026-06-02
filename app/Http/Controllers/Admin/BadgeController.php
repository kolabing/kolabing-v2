<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GamificationBadgeSlug;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBadgeRequest;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\EarnedBadge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Unified admin view over both badge systems (Q4):
 *
 * - System A: rows in `badges` (attendee-flavoured milestones, DB-editable).
 *   Awarded via `badge_awards`.
 * - System B: GamificationBadgeSlug enum (business + community oriented,
 *   metadata is hardcoded display strings on the enum). Awarded via
 *   `earned_badges`.
 *
 * Admin sees both, sectioned by Attendee / Business / Community. System A
 * is editable here; System B is read-only (changing copy lives in the enum
 * file). Award counts are pulled live so admin has visibility into uptake.
 */
class BadgeController extends Controller
{
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
     * Award counts are joined against `earned_badges` × `profiles` and
     * filtered by `profiles.user_type`.
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
            if (! in_array($audience, $slug->audiences(), true)) {
                continue;
            }
            $rows[] = [
                'slug' => $slug->value,
                'name' => $slug->displayName(),
                'description' => $slug->description(),
                'award_count' => (int) ($counts[$slug->value] ?? 0),
                'editable' => false,
            ];
        }

        return $rows;
    }
}
