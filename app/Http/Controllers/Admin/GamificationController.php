<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\CommunityGoal;
use App\Models\CommunityPoints;
use App\Models\CommunityReward;
use App\Models\RewardRedemption;
use App\Services\LeaderboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Read-only gamification oversight for maintainers: a platform overview, the
 * per-community points leaderboard, and the global XP leaderboard. Leaders own
 * goals/rewards/badges in-app; admin only views them. The one writable surface
 * (global partner-reward catalogue) lives in PartnerRewardController.
 */
class GamificationController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboard) {}

    /**
     * Platform overview: global totals plus a per-community breakdown of active
     * goals / rewards / badges, points in circulation, and redemptions.
     */
    public function overview(): View
    {
        $totals = [
            'communities' => Community::query()->count(),
            'active_goals' => CommunityGoal::query()->where('is_active', true)->count(),
            'active_rewards' => CommunityReward::query()->where('is_active', true)->count(),
            'active_badges' => CommunityBadge::query()->where('is_active', true)->count(),
            'points_in_circulation' => (int) CommunityPoints::query()->sum('points'),
            'redemptions' => RewardRedemption::query()->count(),
        ];

        $goalCounts = CommunityGoal::query()->where('is_active', true)
            ->select('community_id', DB::raw('COUNT(*) as c'))
            ->groupBy('community_id')->pluck('c', 'community_id');
        $rewardCounts = CommunityReward::query()->where('is_active', true)
            ->select('community_id', DB::raw('COUNT(*) as c'))
            ->groupBy('community_id')->pluck('c', 'community_id');
        $badgeCounts = CommunityBadge::query()->where('is_active', true)
            ->select('community_id', DB::raw('COUNT(*) as c'))
            ->groupBy('community_id')->pluck('c', 'community_id');
        $pointSums = CommunityPoints::query()
            ->select('community_id', DB::raw('SUM(points) as p'))
            ->groupBy('community_id')->pluck('p', 'community_id');
        $redemptionCounts = RewardRedemption::query()
            ->select('community_id', DB::raw('COUNT(*) as c'))
            ->groupBy('community_id')->pluck('c', 'community_id');

        $communities = Community::query()
            ->orderBy('name')
            ->paginate(30)
            ->through(fn (Community $community): array => [
                'community' => $community,
                'goals' => (int) ($goalCounts[$community->id] ?? 0),
                'rewards' => (int) ($rewardCounts[$community->id] ?? 0),
                'badges' => (int) ($badgeCounts[$community->id] ?? 0),
                'points' => (int) ($pointSums[$community->id] ?? 0),
                'redemptions' => (int) ($redemptionCounts[$community->id] ?? 0),
            ]);

        return view('admin.gamification.overview', [
            'totals' => $totals,
            'communities' => $communities,
        ]);
    }

    /**
     * Per-community leaderboard picker + the selected community's points
     * leaderboard (members ranked by community points, with tier + badge count).
     */
    public function communityLeaderboard(?Community $community = null): View
    {
        $communities = Community::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.gamification.community-leaderboard', [
            'communities' => $communities,
            'selected' => $community,
            'rows' => $community !== null
                ? $this->leaderboard->getCommunityPointsLeaderboard($community, 100)
                : collect(),
        ]);
    }

    /**
     * Global XP leaderboard (all users ranked by global XP).
     */
    public function globalLeaderboard(): View
    {
        return view('admin.gamification.global-leaderboard', [
            'rows' => $this->leaderboard->getGlobalLeaderboard(100),
        ]);
    }
}
