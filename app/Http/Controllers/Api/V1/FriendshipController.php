<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendFriendRequestRequest;
use App\Http\Resources\Api\V1\FriendResource;
use App\Models\Friendship;
use App\Models\Profile;
use App\Services\FriendshipService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function __construct(
        private readonly FriendshipService $friendships
    ) {}

    /**
     * POST /api/v1/friends/requests — send a friend request (idempotent).
     */
    public function store(SendFriendRequestRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $friendship = $this->friendships->sendRequest($profile, $request->validated()['profile_id']);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        $friendship->load(['requester', 'addressee']);

        return response()->json([
            'success' => true,
            'data' => new FriendResource($friendship, $profile->id),
        ], 201);
    }

    /**
     * POST /api/v1/friends/requests/{friendship}/accept — addressee only.
     */
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $friendship = $this->friendships->accept($friendship, $profile);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        $friendship->load(['requester', 'addressee']);

        return response()->json([
            'success' => true,
            'data' => new FriendResource($friendship, $profile->id),
        ]);
    }

    /**
     * POST /api/v1/friends/requests/{friendship}/decline — addressee only.
     */
    public function decline(Request $request, Friendship $friendship): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $this->friendships->decline($friendship, $profile);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * DELETE /api/v1/friends/{profile} — remove an accepted friendship (either side).
     */
    public function destroy(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $actor */
        $actor = $request->user();

        try {
            $this->friendships->remove($actor, $profile->id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * POST /api/v1/friends/{profile}/block.
     */
    public function block(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $actor */
        $actor = $request->user();

        try {
            $friendship = $this->friendships->block($actor, $profile->id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        $friendship->load(['requester', 'addressee']);

        return response()->json([
            'success' => true,
            'data' => new FriendResource($friendship, $actor->id),
        ]);
    }

    /**
     * POST /api/v1/friends/{profile}/unblock.
     */
    public function unblock(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $actor */
        $actor = $request->user();

        try {
            $this->friendships->unblock($actor, $profile->id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * GET /api/v1/me/friends — accepted friends (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('limit', '25'), 100);
        $paginator = $this->friendships->friends($profile, $perPage);

        $friends = collect($paginator->items())
            ->map(fn (Friendship $f): FriendResource => new FriendResource($f, $profile->id))
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'friends' => $friends,
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
     * GET /api/v1/me/friends/requests — incoming + sent pending.
     */
    public function requests(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $pending = $this->friendships->pendingRequests($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'incoming' => $pending['incoming']
                    ->map(fn (Friendship $f): FriendResource => new FriendResource($f, $profile->id))
                    ->all(),
                'sent' => $pending['sent']
                    ->map(fn (Friendship $f): FriendResource => new FriendResource($f, $profile->id))
                    ->all(),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/friends/suggested — profiles sharing >= 3 attended events.
     */
    public function suggested(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $suggestions = $this->friendships->suggested($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions->map(fn (Profile $candidate): array => [
                    'id' => $candidate->id,
                    'name' => $candidate->getExtendedProfile()?->name
                        ?? \Illuminate\Support\Str::before($candidate->email, '@'),
                    'avatar_url' => $candidate->avatar_url
                        ?? ($candidate->getExtendedProfile()->profile_photo ?? null),
                    'user_type' => $candidate->user_type->value,
                ])->all(),
            ],
        ]);
    }

    private function domainError(DomainException $e): JsonResponse
    {
        $status = match ($e->getMessage()) {
            'forbidden' => 403,
            'cannot_friend_self', 'cannot_block_self', 'blocked', 'not_pending' => 422,
            'not_friends', 'not_blocked' => 404,
            default => 422,
        };

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], $status);
    }
}
