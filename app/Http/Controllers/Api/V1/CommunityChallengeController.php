<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncCommunityChallengesRequest;
use App\Http\Resources\Api\V1\ChallengeResource;
use App\Http\Resources\Api\V1\CommunityChallengeResource;
use App\Models\Community;
use App\Models\CommunityChallenge;
use App\Models\Profile;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The challenge library, and which of it a community plays (kolabing-app#150).
 *
 * Kolabing supplies the library; the community decides what its events play and
 * how strictly. That division is the whole point — not every community has the
 * same goals, and until now every one of them got an identical list.
 */
class CommunityChallengeController extends Controller
{
    public function __construct(
        private readonly ChallengeService $challengeService,
    ) {}

    /**
     * The library a leader picks from.
     *
     * GET /api/v1/challenge-library
     *
     * Open to any signed-in profile: it is a catalogue of what Kolabing offers,
     * not anyone's data.
     */
    public function library(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('limit', '50'), 100);
        $paginator = $this->challengeService->library($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'challenges' => ChallengeResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * What this community has chosen.
     *
     * GET /api/v1/communities/{community}/challenges
     *
     * Readable by anyone signed in, because a member should be able to see what
     * their community plays without being able to change it. An **empty list
     * means no curation**, which is not the same as "no challenges" — the
     * community's events fall back to the whole library. The
     * `curated` flag says which of the two it is, so a client never has to guess.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        $rows = CommunityChallenge::query()
            ->where('community_id', $community->id)
            ->with('challenge')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'curated' => $rows->isNotEmpty(),
                'challenges' => CommunityChallengeResource::collection($rows),
            ],
        ]);
    }

    /**
     * Replace the whole set.
     *
     * PUT /api/v1/communities/{community}/challenges
     */
    public function sync(SyncCommunityChallengesRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to change this community\'s challenges.'),
            ], 403);
        }

        /** @var array<int, array{challenge_id: string, allow_repeat_with_same_person?: bool, requires_new_person?: bool}> $selections */
        $selections = $request->validated('challenges');

        $rows = $this->challengeService->syncForCommunity($community, $selections);

        return response()->json([
            'success' => true,
            'data' => [
                'curated' => $rows->isNotEmpty(),
                'challenges' => CommunityChallengeResource::collection($rows),
            ],
        ]);
    }
}
