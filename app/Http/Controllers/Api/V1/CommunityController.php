<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Exceptions\CommunityLimitReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Http\Requests\Api\V1\StoreCommunityRequest;
use App\Http\Requests\Api\V1\UpdateCommunityRequest;
use App\Http\Resources\Api\V1\CommunityDiscoverResource;
use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use App\Services\CommunityMembershipHydrator;
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
        private readonly CommunityMembershipHydrator $membershipHydrator,
    ) {}

    /**
     * GET /api/v1/me/communities — communities I own (leader view).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $communities = $profile->ownedCommunities()
            ->with('communityProfile')
            ->latest()
            ->get();

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
            ->with('owner.businessProfile', 'owner.communityProfile');

        // The community-type vocabulary is the REAL 17-slug list
        // (CommunityOnboardingRequest::COMMUNITY_TYPES), the same one communities
        // pick at sign-up and attendees pick as interests. NOT the 5-value
        // App\Enums\CommunityType placeholder.
        $communityTypes = CommunityOnboardingRequest::COMMUNITY_TYPES;

        $type = $request->query('type');
        $hasTypeFilter = is_string($type) && $type !== '' && in_array($type, $communityTypes, true);

        // Viewer interests (17-slug community-type vocabulary) drive interest-first
        // ranking when no explicit ?type filter is supplied. Matched against
        // communities.type, which now carries the same 17-slug vocabulary.
        $interests = $profile->interests ?? [];
        $interests = is_array($interests)
            ? array_values(array_filter($interests, static fn ($v): bool => in_array($v, $communityTypes, true)))
            : [];

        if ($hasTypeFilter) {
            $query->where('type', $type);
        } elseif ($interests !== []) {
            // Interest-match-first: rank communities whose type is in the
            // viewer's interests above the rest, then featured, then members.
            $placeholders = implode(',', array_fill(0, count($interests), '?'));
            $query->orderByRaw("CASE WHEN type IN ($placeholders) THEN 0 ELSE 1 END", $interests);
        }

        $query->orderByDesc('is_featured')
            ->orderByDesc('active_members_count')
            ->orderBy('name');

        $communities = $query->paginate($perPage);

        // Tag each row with whether it matched a viewer interest (only meaningful
        // when interest-ranking is active, i.e. no explicit ?type filter).
        $matchInterests = $hasTypeFilter ? [] : $interests;
        foreach ($communities->items() as $community) {
            $community->setAttribute(
                'interest_matched',
                in_array($community->type, $matchInterests, true)
            );
        }

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

        $this->membershipHydrator->hydrate($profile, $memberships);

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

    /**
     * GET /api/v1/communities/{community}/invite — shareable join link.
     *
     * Owner / can_manage only. For open communities returns the canonical slug
     * link. For invite_only communities also returns a pre-authorizing token
     * (lazily minted) and the token-bearing url for POST /communities/join/{token}.
     */
    public function invite(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $isInviteOnly = $community->join_policy === JoinPolicy::InviteOnly;
        $token = $isInviteOnly ? $community->ensureInviteToken() : null;

        return response()->json([
            'success' => true,
            'data' => [
                'community_id' => $community->id,
                'slug' => $community->slug,
                'join_policy' => $community->join_policy->value,
                'invite_url' => $community->inviteUrl(),
                'token' => $token,
                'token_url' => $isInviteOnly ? $community->inviteUrlWithToken() : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/communities/join/{token} — join via an invite token.
     *
     * Resolves the invite_only community by its token and adds the caller as a
     * member, bypassing the invite_only gate. Open communities should use the
     * plain POST /communities/{community}/join instead.
     */
    public function joinByToken(Request $request, string $token): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $community = Community::query()->where('invite_token', $token)->first();

        if ($community === null) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_invite',
                'message' => __('This invite link is invalid or has expired.'),
            ], 404);
        }

        // Token pre-authorizes the join regardless of join policy.
        $member = $this->memberService->addMember($community, $profile->id);
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
