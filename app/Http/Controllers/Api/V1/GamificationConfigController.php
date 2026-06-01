<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\XpEarnRule;
use App\Models\XpLevel;
use App\Services\Admin\XpEarnRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Single read endpoint the app consults at startup to render the XP economy
 * (levels, per-action awards) from server values, rather than hardcoding them
 * in Dart. Cached; busted on any admin write to xp_earn_rules or xp_levels.
 *
 * GET /api/v1/gamification/config
 */
class GamificationConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember(
            XpEarnRuleService::CACHE_KEY,
            now()->addHour(),
            fn (): array => $this->build(),
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $levels = XpLevel::query()
            ->orderBy('position')
            ->orderBy('number')
            ->get()
            ->map(fn (XpLevel $level): array => [
                'number' => $level->number,
                'title' => $level->title,
                'min_xp' => $level->min_xp,
                'max_xp' => $level->max_xp,
                'color' => $level->color,
            ])
            ->values()
            ->all();

        $earnRules = XpEarnRule::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('event_type')
            ->get()
            ->map(fn (XpEarnRule $rule): array => [
                'event_type' => $rule->event_type,
                'points' => $rule->points,
                'label' => $rule->label,
            ])
            ->values()
            ->all();

        return [
            'xp_levels' => $levels,
            'earn_rules' => $earnRules,
        ];
    }
}
