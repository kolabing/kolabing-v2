<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FriendResource;
use App\Models\Profile;
use App\Services\FriendshipService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function __construct(
        private readonly FriendshipService $friendships,
    ) {}

    /**
     * POST /api/v1/friends/{profile} — send a friend request (auto-accepts a
     * reverse pending request).
     */
    public function store(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        try {
            $friendship = $this->friendships->request($viewer, $profile);
        } catch (DomainException $e) {
            return $this->error($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => [
                'friend_status' => $this->friendships->statusFor($viewer, $profile),
                'friends_count' => $this->friendships->friendsCountFor($profile),
                'accepted' => $friendship->isAccepted(),
            ],
        ], 201);
    }

    /**
     * POST /api/v1/friends/{profile}/accept — accept an incoming request.
     */
    public function accept(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        try {
            $this->friendships->accept($viewer, $profile);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'friend_status' => $this->friendships->statusFor($viewer, $profile),
                'friends_count' => $this->friendships->friendsCountFor($profile),
            ],
        ]);
    }

    /**
     * POST /api/v1/friends/{profile}/decline — decline an incoming request.
     */
    public function decline(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $this->friendships->decline($viewer, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'friend_status' => $this->friendships->statusFor($viewer, $profile),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/friends/{profile} — remove a friend or cancel an outgoing
     * request.
     */
    public function destroy(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $this->friendships->remove($viewer, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'friend_status' => $this->friendships->statusFor($viewer, $profile),
                'friends_count' => $this->friendships->friendsCountFor($profile),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/friends — paginated accepted friends.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 100);
        $friends = $this->friendships->friendsOf($viewer, $perPage);

        return response()->json([
            'success' => true,
            'data' => FriendResource::collection($friends),
            'meta' => [
                'current_page' => $friends->currentPage(),
                'last_page' => $friends->lastPage(),
                'per_page' => $friends->perPage(),
                'total' => $friends->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/friend-requests — incoming pending requests + count.
     */
    public function requests(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $incoming = $this->friendships->incomingRequests($viewer);

        return response()->json([
            'success' => true,
            'data' => FriendResource::collection($incoming),
            'count' => $incoming->count(),
        ]);
    }

    /**
     * 422 JSON error in the codebase's standard shape.
     */
    private function error(string $code, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $code,
        ], $status);
    }
}
