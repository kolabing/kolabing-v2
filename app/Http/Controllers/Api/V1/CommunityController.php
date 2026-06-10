<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use App\Exceptions\CommunityLimitReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityRequest;
use App\Http\Requests\Api\V1\UpdateCommunityRequest;
use App\Http\Resources\Api\V1\CommunityDiscoverResource;
use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use App\Services\CommunityService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityService $communityService,
        private readonly CommunityMemberService $memberService,
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
     * GET /api/v1/communities/discover — public communities an attendee can join.
     *
     * Lists PUBLIC (open join_policy) communities, EXCLUDING any the viewer
     * already owns or is an active member of. Ordered featured-first, then by
     * active member count desc. Optional ?type= filter (CommunityType) is the
     * hook for later interest-ranking.
     */
    public function discover(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 50);

        $query = Community::query()
            ->where('join_policy', JoinPolicy::Open->value)
            ->where('owner_profile_id', '!=', $profile->id)
            ->whereNotExists(function ($sub) use ($profile): void {
                $sub->select(DB::raw(1))
                    ->from('community_members')
                    ->whereColumn('community_members.community_id', 'communities.id')
                    ->where('community_members.profile_id', $profile->id)
                    ->where('community_members.status', CommunityMemberStatus::Active->value);
            })
            ->withCount(['members as active_members_count' => function ($sub): void {
                $sub->where('status', CommunityMemberStatus::Active->value);
            }])
            ->with('owner.businessProfile', 'owner.communityProfile')
            ->orderByDesc('is_featured')
            ->orderByDesc('active_members_count')
            ->orderBy('name');

        $type = $request->query('type');
        if (is_string($type) && $type !== '' && in_array($type, CommunityType::values(), true)) {
            $query->where('type', $type);
        }

        $communities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CommunityDiscoverResource::collection($communities->items()),
            'meta' => [
                'current_page' => $communities->currentPage(),
                'last_page' => $communities->lastPage(),
                'per_page' => $communities->perPage(),
                'total' => $communities->total(),
            ],
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
