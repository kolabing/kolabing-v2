<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\MissionWithProgress;
use App\Enums\ChallengeAudience;
use App\Enums\MissionTrigger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MissionResource;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Profile;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MissionController extends Controller
{
    /**
     * List the authenticated viewer's role-relevant LIVE missions with the
     * viewer's progress for the current period.
     *
     * GET /api/v1/me/missions
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $now = Carbon::now();

        $missions = Challenge::query()
            ->where('is_system', true)
            ->whereNull('event_id')
            ->whereNotNull('trigger_action')
            ->whereIn('audience', $this->audiencesFor($viewer))
            ->whereIn('trigger_action', $this->liveTriggerValues())
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('category')
            ->orderBy('points')
            ->orderBy('id')
            ->get();

        $progressByChallenge = $this->currentProgressFor($viewer, $missions, $now);

        $dtos = $missions->map(function (Challenge $mission) use ($progressByChallenge, $now): MissionWithProgress {
            $periodKey = MissionService::periodKeyFor($mission->repeat_interval, $now);

            return new MissionWithProgress(
                mission: $mission,
                progress: $progressByChallenge[$mission->id] ?? null,
                periodKey: $periodKey,
            );
        })->all();

        return response()->json([
            'success' => true,
            'data' => [
                'missions' => MissionResource::collection($dtos),
            ],
        ]);
    }

    /**
     * The audience values a mission may carry to be visible to this viewer.
     * `both` means business + community (never attendee).
     *
     * @return list<string>
     */
    private function audiencesFor(Profile $viewer): array
    {
        return match (true) {
            $viewer->isBusiness() => [ChallengeAudience::Business->value, ChallengeAudience::Both->value],
            $viewer->isCommunity() => [ChallengeAudience::Community->value, ChallengeAudience::Both->value],
            $viewer->isAttendee() => [ChallengeAudience::Attendee->value],
            default => [],
        };
    }

    /**
     * The wire values of every LIVE mission trigger (the ones that fire today).
     *
     * @return list<string>
     */
    private function liveTriggerValues(): array
    {
        return array_values(array_map(
            static fn (MissionTrigger $trigger): string => $trigger->value,
            array_filter(MissionTrigger::cases(), static fn (MissionTrigger $trigger): bool => $trigger->isLive()),
        ));
    }

    /**
     * The viewer's current-period progress rows, keyed by challenge id. Each
     * mission's period_key is resolved with the same helper `record()` uses,
     * and rows are fetched with one query per distinct period key (almost
     * always 1-2 distinct values) instead of loading every historical row.
     *
     * @param  \Illuminate\Support\Collection<int, Challenge>  $missions
     * @return array<string, ChallengeProgress>
     */
    private function currentProgressFor(Profile $viewer, \Illuminate\Support\Collection $missions, Carbon $now): array
    {
        if ($missions->isEmpty()) {
            return [];
        }

        $idsByPeriodKey = $missions
            ->mapToGroups(fn (Challenge $mission): array => [
                MissionService::periodKeyFor($mission->repeat_interval, $now) => $mission->id,
            ]);

        $byChallenge = [];

        foreach ($idsByPeriodKey as $periodKey => $challengeIds) {
            $rows = ChallengeProgress::query()
                ->where('profile_id', $viewer->id)
                ->where('period_key', $periodKey)
                ->whereIn('challenge_id', $challengeIds)
                ->get();

            foreach ($rows as $row) {
                $byChallenge[$row->challenge_id] = $row;
            }
        }

        return $byChallenge;
    }
}
