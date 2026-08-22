<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommunityJoinRequestResource;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use App\Services\CommunityJoinRequestService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityJoinRequestController extends Controller
{
    public function __construct(
        private readonly CommunityJoinRequestService $service,
    ) {}

    /**
     * POST /api/v1/communities/{community}/join-requests — attendee requests to
     * join an invite_only community. Open communities have no request flow
     * (422 community_is_open — the client should call the /join endpoint).
     * Idempotent: re-requesting while pending returns the existing request (200).
     */
    public function store(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        // Already an active member → nothing to request.
        $isMember = $community->members()
            ->where('profile_id', $profile->id)
            ->where('status', \App\Enums\CommunityMemberStatus::Active->value)
            ->exists();

        if ($isMember) {
            return response()->json([
                'success' => false,
                'error' => 'already_member',
                'message' => __('You are already a member of this community.'),
            ], 422);
        }

        $validated = $request->validate([
            'answers' => ['sometimes', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'uuid'],
            'answers.*.answer' => ['required_with:answers', 'string', 'max:2000'],
        ]);

        try {
            $alreadyPending = $community->joinRequests()
                ->where('profile_id', $profile->id)
                ->where('status', \App\Enums\JoinRequestStatus::Pending->value)
                ->exists();

            $joinRequest = $this->service->request(
                $community,
                $profile,
                $validated['answers'] ?? [],
            );
        } catch (DomainException $e) {
            // Two distinct refusals, so the client can tell "use /join instead"
            // apart from "you left a required question blank".
            if ($e->getMessage() === 'missing_required_answers') {
                return response()->json([
                    'success' => false,
                    'error' => 'missing_required_answers',
                    'message' => __('Please answer every required question.'),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error' => 'community_is_open',
                'message' => __('This community is open. You can join directly.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityJoinRequestResource($joinRequest),
        ], $alreadyPending ? 200 : 201);
    }

    /**
     * GET /api/v1/communities/{community}/join-requests — leader/manager lists
     * the community's pending requests (authorized like the roster surface).
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $requests = $this->service->pending($community);

        return response()->json([
            'success' => true,
            'data' => CommunityJoinRequestResource::collection($requests),
        ]);
    }

    /**
     * POST /api/v1/join-requests/{joinRequest}/approve — leader/manager approves.
     */
    public function approve(Request $request, CommunityJoinRequest $joinRequest): JsonResponse
    {
        return $this->decide($request, $joinRequest, approve: true);
    }

    /**
     * POST /api/v1/join-requests/{joinRequest}/decline — leader/manager declines.
     */
    public function decline(Request $request, CommunityJoinRequest $joinRequest): JsonResponse
    {
        return $this->decide($request, $joinRequest, approve: false);
    }

    private function decide(Request $request, CommunityJoinRequest $joinRequest, bool $approve): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $joinRequest->loadMissing('community');

        if ($joinRequest->community === null) {
            return $this->notFound();
        }

        if ($profile->cannot('manage', $joinRequest->community)) {
            return $this->forbidden();
        }

        try {
            $joinRequest = $approve
                ? $this->service->approve($joinRequest, $profile)
                : $this->service->decline($joinRequest, $profile);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => 'already_decided',
                'message' => __('This request has already been decided.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityJoinRequestResource($joinRequest),
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
            'message' => __('Join request not found.'),
        ], 404);
    }
}
