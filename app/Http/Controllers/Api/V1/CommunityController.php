<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Exceptions\CommunityLimitReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityRequest;
use App\Http\Requests\Api\V1\UpdateCommunityRequest;
use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Http\Resources\Api\V1\CommunityProfileAggregateResource;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use App\Services\CommunityProfileService;
use App\Services\CommunityService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityService $communityService,
        private readonly CommunityMemberService $memberService,
        private readonly CommunityProfileService $profileService,
    ) {}

    /**
     * GET /api/v1/me/communities — communities I own (leader view).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $communities = $profile->ownedCommunities()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => CommunityResource::collection($communities),
        ]);
    }

    /**
     * POST /api/v1/communities — create a community (free cap enforced).
     */
    public function store(StoreCommunityRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $community = $this->communityService->create($profile, $request->validated());
        } catch (CommunityLimitReachedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'community_limit_reached',
                'message' => __('You have reached the limit of free communities. Community Premium is required to create more.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityResource($community),
        ], 201);
    }

    /**
     * GET /api/v1/communities/{community}.
     */
    public function show(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CommunityResource($community),
        ]);
    }

    /**
     * GET /api/v1/communities/{community}/profile — the rich aggregate
     * community public-profile (Batch 6). Members get viewer tier + chapter
     * rank + member-visible chats; non-members get the public subset.
     */
    public function profile(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $payload = $this->profileService->build($community, $profile);

        return response()->json([
            'success' => true,
            'data' => new CommunityProfileAggregateResource($payload),
        ]);
    }

    /**
     * PATCH /api/v1/communities/{community} (owner / can_manage).
     */
    public function update(UpdateCommunityRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $community = $this->communityService->update($community, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityResource($community),
        ]);
    }

    /**
     * GET /api/v1/me/memberships — communities I belong to + my tier in each.
     */
    public function myMemberships(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $memberships = $profile->communityMemberships()
            ->where('status', CommunityMemberStatus::Active->value)
            ->with(['community', 'tier'])
            ->get();

        $data = $memberships->map(fn ($member) => [
            'community' => new CommunityResource($member->community),
            'tier' => $member->tier ? new CommunityTierResource($member->tier) : null,
            'can_manage' => $member->can_manage,
            'status' => $member->status->value,
            'joined_at' => $member->joined_at?->toIso8601String(),
        ])->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/join — self-join (open only).
     */
    public function join(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $member = $this->memberService->join($community, $profile);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => 'invite_only',
                'message' => __('This community is invite only. Ask a community manager to add you.'),
            ], 403);
        }

        $member->load(['tier', 'profile']);

        return response()->json([
            'success' => true,
            'data' => new CommunityMemberResource($member),
        ], 201);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }
}
