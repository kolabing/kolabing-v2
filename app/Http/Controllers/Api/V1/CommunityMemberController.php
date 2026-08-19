<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MissionTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkUpdateCommunityMembersRequest;
use App\Http\Requests\Api\V1\StoreCommunityMemberRequest;
use App\Http\Requests\Api\V1\UpdateCommunityMemberRequest;
use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use App\Services\CommunityRosterQuery;
use App\Services\HandleService;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityMemberController extends Controller
{
    public function __construct(
        private readonly CommunityMemberService $memberService,
        private readonly CommunityRosterQuery $rosterQuery,
        private readonly HandleService $handleService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * GET /api/v1/communities/{community}/members
     *
     * Paginated roster with nested tier + profile + engagement metrics.
     * Manage-gated: the payload carries member emails, so it is not a public
     * roster. Filters: search, status, tier_id, can_manage, sort, direction.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $perPage = min(max((int) $request->query('limit', '25'), 1), 100);
        $canManage = $request->query('can_manage');

        $paginator = $this->memberService->roster($community, $perPage, [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'tier_id' => $request->query('tier_id'),
            'can_manage' => $canManage === null ? null : filter_var($canManage, FILTER_VALIDATE_BOOL),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'members' => CommunityMemberResource::collection($paginator->items()),
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
     * GET /api/v1/communities/{community}/members/{member} — the roster drawer.
     *
     * Member + engagement metrics + a capped activity timeline. Not paginated:
     * this is a drawer, not a page.
     */
    public function show(Request $request, Community $community, CommunityMember $member): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($member->community_id !== $community->id) {
            return $this->notFound();
        }

        // Re-read through the roster query so the drawer carries the same
        // engagement metrics the list row does.
        $member = $this->rosterQuery->base($community)
            ->where('community_members.id', $member->id)
            ->firstOrFail();

        $activity = CommunityPointLedger::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $member->profile_id)
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (CommunityPointLedger $row): array => [
                'id' => $row->id,
                'points' => $row->points,
                'source' => $row->source,
                'description' => $row->description,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => new CommunityMemberResource($member),
                'activity' => $activity,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/communities/{community}/members — bulk tier / can_manage / status.
     */
    public function bulkUpdate(BulkUpdateCommunityMembersRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        $memberIds = $data['member_ids'];
        unset($data['member_ids']);

        if (array_key_exists('tier_id', $data) && $data['tier_id'] !== null
            && ! $community->tiers()->whereKey($data['tier_id'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->memberService->bulkUpdate($community, $memberIds, $data),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/members — invite/add (owner / can_manage).
     */
    public function store(StoreCommunityMemberRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $data = $request->validated();

        // Resolve the target: explicit profile_id, the email on a Kolabing
        // account, or an email/@handle passed via `identifier`.
        $targetProfileId = $data['profile_id'] ?? null;

        if ($targetProfileId === null) {
            $target = $this->resolveTarget($data);

            if ($target === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'profile_not_found',
                    'message' => __('No Kolabing account found for that email or @handle.'),
                ], 404);
            }

            $targetProfileId = $target->id;
        }

        $tierId = $data['tier_id'] ?? null;

        if ($tierId !== null && ! $community->tiers()->whereKey($tierId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        $member = $this->memberService->addMember($community, $targetProfileId, $tierId);
        $member->load(['tier', 'profile']);

        // Missions: the inviter (community owner / manager) progresses
        // members_invited, but only on a genuinely new membership so repeated
        // invites of the same person never re-fire. Audience scoping limits this
        // to the community's missions. Guarded — never breaks the invite.
        if ($member->wasRecentlyCreated) {
            $this->missionService->recordSafely(
                $profile,
                MissionTrigger::MembersInvited,
                1,
                ['reference_id' => $member->id],
            );
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityMemberResource($member),
        ], 201);
    }

    /**
     * PATCH /api/v1/communities/{community}/members/{member} — tier/can_manage/status.
     */
    public function update(UpdateCommunityMemberRequest $request, Community $community, CommunityMember $member): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($member->community_id !== $community->id) {
            return $this->notFound();
        }

        $data = $request->validated();

        if (array_key_exists('tier_id', $data) && $data['tier_id'] !== null
            && ! $community->tiers()->whereKey($data['tier_id'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        $member = $this->memberService->updateMember($member, $data);
        $member->load(['tier', 'profile']);

        return response()->json([
            'success' => true,
            'data' => new CommunityMemberResource($member),
        ]);
    }

    /**
     * DELETE /api/v1/communities/{community}/members/{member} — soft remove.
     */
    public function destroy(Request $request, Community $community, CommunityMember $member): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($member->community_id !== $community->id) {
            return $this->notFound();
        }

        $this->memberService->remove($member);

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * Resolve the target profile from validated add-member data. Accepts an
     * exact `email`, or an `identifier` that is either an email or an @handle.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveTarget(array $data): ?Profile
    {
        if (! empty($data['email'])) {
            return Profile::where('email', $data['email'])->first();
        }

        $identifier = isset($data['identifier']) ? trim((string) $data['identifier']) : '';

        if ($identifier === '') {
            return null;
        }

        // An email-looking identifier (contains @ but not as a leading handle
        // marker, and has a dot) resolves by email; otherwise by @handle.
        if (str_contains($identifier, '@') && str_contains($identifier, '.') && ! str_starts_with($identifier, '@')) {
            return Profile::where('email', mb_strtolower($identifier))->first();
        }

        return $this->handleService->resolve($identifier);
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
            'message' => __('Member not found in this community.'),
        ], 404);
    }
}
