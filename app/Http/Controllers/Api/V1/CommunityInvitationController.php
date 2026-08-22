<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityInvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityInvitationRequest;
use App\Http\Resources\Api\V1\CommunityInvitationResource;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\Profile;
use App\Services\CommunityInvitationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityInvitationController extends Controller
{
    public function __construct(private readonly CommunityInvitationService $invitations) {}

    /**
     * GET /api/v1/communities/{community}/invitations?status=pending|all
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $query = $community->invitations()->with('tier')->latest();

        if ($request->query('status') !== 'all') {
            $query->where('status', CommunityInvitationStatus::Pending->value);
        }

        return response()->json([
            'success' => true,
            'data' => CommunityInvitationResource::collection($query->get()),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/invitations — invite by email.
     */
    public function store(StoreCommunityInvitationRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        $tierId = $data['tier_id'] ?? null;

        if ($tierId !== null && ! $community->tiers()->whereKey($tierId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        // Per-row results so the panel can say "8 invited, 2 already members".
        $results = [];

        foreach ($data['emails'] as $email) {
            $outcome = $this->invitations->invite($community, $email, $tierId, $profile);

            $results[] = [
                'email' => mb_strtolower(trim((string) $email)),
                'status' => $outcome['status'],
                'invitation' => $outcome['invitation']
                    ? new CommunityInvitationResource($outcome['invitation'])
                    : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'invited' => count(array_filter($results, fn (array $r): bool => $r['status'] === 'invited')),
                'already_members' => count(array_filter($results, fn (array $r): bool => $r['status'] === 'already_member')),
            ],
        ], 201);
    }

    /**
     * POST /api/v1/invitations/{invitation}/resend
     */
    public function resend(Request $request, CommunityInvitation $invitation): JsonResponse
    {
        if ($guard = $this->guardManage($request, $invitation)) {
            return $guard;
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($this->invitations->resend($invitation)),
        ]);
    }

    /**
     * DELETE /api/v1/invitations/{invitation} — revoke.
     */
    public function destroy(Request $request, CommunityInvitation $invitation): JsonResponse
    {
        if ($guard = $this->guardManage($request, $invitation)) {
            return $guard;
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($this->invitations->revoke($invitation)),
        ]);
    }

    /**
     * POST /api/v1/invitations/accept/{token} — the invitee redeems it.
     * Auth required; the token is the authorization.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $invitation = CommunityInvitation::query()->where('token', $token)->first();

        if ($invitation === null) {
            return response()->json([
                'success' => false,
                'error' => 'invitation_not_found',
                'message' => __('This invitation link is not valid.'),
            ], 404);
        }

        try {
            $invitation = $this->invitations->accept($invitation, $profile);
        } catch (DomainException) {
            return response()->json([
                'success' => false,
                'error' => 'invitation_not_claimable',
                'message' => __('This invitation has expired or has already been used.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($invitation),
        ]);
    }

    private function guardManage(Request $request, CommunityInvitation $invitation): ?JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $invitation->loadMissing('community');

        if ($invitation->community === null) {
            return response()->json([
                'success' => false,
                'message' => __('Invitation not found.'),
            ], 404);
        }

        return $profile->cannot('manage', $invitation->community) ? $this->forbidden() : null;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }
}
