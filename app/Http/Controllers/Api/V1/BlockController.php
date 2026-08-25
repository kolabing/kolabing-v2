<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Blocking abusive users (App Review Guideline 1.2). Blocked users' content is
 * removed from the blocker's feed; the developer is emailed on each new block.
 */
class BlockController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
    ) {}

    /**
     * List the profile IDs the authenticated user has blocked.
     *
     * GET /api/v1/me/blocks
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        return response()->json([
            'data' => $this->moderation->idsBlockedBy($viewer),
        ]);
    }

    /**
     * Block a profile. Idempotent — re-blocking returns success without error.
     *
     * POST /api/v1/me/blocks/{profile}
     */
    public function store(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        if ($viewer->id === $profile->id) {
            return response()->json([
                'success' => false,
                'message' => __('You cannot block yourself'),
            ], 422);
        }

        $this->moderation->block($viewer, $profile);

        return response()->json(['success' => true], 201);
    }

    /**
     * Unblock a profile.
     *
     * DELETE /api/v1/me/blocks/{profile}
     */
    public function destroy(Request $request, Profile $profile): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $this->moderation->unblock($viewer, $profile);

        return response()->json(['success' => true]);
    }
}
