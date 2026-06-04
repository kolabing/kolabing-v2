<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityMemberRequest;
use App\Http\Requests\Api\V1\UpdateCommunityMemberRequest;
use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityMemberController extends Controller
{
    public function __construct(
        private readonly CommunityMemberService $memberService
    ) {}

    /**
     * GET /api/v1/communities/{community}/members (paginated, nested tier+profile).
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        $perPage = min((int) $request->query('limit', '25'), 100);
        $paginator = $this->memberService->roster($community, $perPage);

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

        // Resolve the target: explicit profile_id, or the email on a Kolabing account.
        $targetProfileId = $data['profile_id'] ?? null;

        if ($targetProfileId === null) {
            $target = Profile::where('email', $data['email'])->first();

            if ($target === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'profile_not_found',
                    'message' => __('No Kolabing account found for that email.'),
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
