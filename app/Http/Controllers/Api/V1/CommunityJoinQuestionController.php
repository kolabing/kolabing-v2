<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityJoinQuestionResource;
use App\Models\Community;
use App\Models\CommunityJoinQuestion;
use App\Models\Profile;
use App\Services\CommunityJoinQuestionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The membership questions a community asks (kolabing-app#138).
 *
 * Reading the active set is open to any signed-in profile — an applicant has to
 * see the questions before they can answer them. Everything that changes the
 * set is gated on the `manage` ability, matching CommunityMemberController.
 */
class CommunityJoinQuestionController extends Controller
{
    public function __construct(
        private readonly CommunityJoinQuestionService $service
    ) {}

    /**
     * GET /api/v1/communities/{community}/join-questions
     *
     * The set an applicant should answer, in display order. Open to anyone
     * signed in; retired questions are not included.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'questions' => CommunityJoinQuestionResource::collection(
                    $this->service->activeFor($community)
                ),
                'max_active' => CommunityJoinQuestion::MAX_ACTIVE,
            ],
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/join-questions
     */
    public function store(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:280'],
            'required' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:1', 'max:'.CommunityJoinQuestion::MAX_ACTIVE],
        ]);

        try {
            $question = $this->service->create(
                $community,
                $validated['prompt'],
                (bool) ($validated['required'] ?? true),
                $validated['position'] ?? null,
            );
        } catch (DomainException) {
            return response()->json([
                'success' => false,
                'error' => 'too_many_questions',
                'message' => __('A community can ask at most :count questions.', [
                    'count' => CommunityJoinQuestion::MAX_ACTIVE,
                ]),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityJoinQuestionResource($question),
        ], 201);
    }

    /**
     * PATCH /api/v1/communities/{community}/join-questions/{question}
     */
    public function update(
        Request $request,
        Community $community,
        CommunityJoinQuestion $question
    ): JsonResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($question->community_id !== $community->id) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'prompt' => ['sometimes', 'string', 'max:280'],
            'required' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:1', 'max:'.CommunityJoinQuestion::MAX_ACTIVE],
        ]);

        return response()->json([
            'success' => true,
            'data' => new CommunityJoinQuestionResource(
                $this->service->update($question, $validated)
            ),
        ]);
    }

    /**
     * DELETE /api/v1/communities/{community}/join-questions/{question}
     *
     * Retires the question. It stops being asked; its existing answers stay
     * readable, so an older application still makes sense to whoever reviews it.
     */
    public function destroy(
        Request $request,
        Community $community,
        CommunityJoinQuestion $question
    ): JsonResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($question->community_id !== $community->id) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityJoinQuestionResource(
                $this->service->retire($question)
            ),
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Question not found for this community.'),
        ], 404);
    }
}
