<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListSuggestionsRequest;
use App\Http\Resources\Api\V1\SuggestionResource;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\Suggestions\SuggestionReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The suggestion feed (BE-NF-28). Thin by design: which rows are live lives in
 * SuggestionReader, the identity blur and the locale-aware copy live in
 * SuggestionResource, and ownership lives in KolabSuggestionPolicy.
 *
 * Authorization is spelled out with `cannot()` plus an explicit 403 body rather
 * than `authorize()`, matching every other controller in Api/V1 so a client sees
 * the same `{success, message}` envelope for a refusal here as anywhere else.
 */
class SuggestionController extends Controller
{
    public function __construct(
        private readonly SuggestionReader $suggestionReader,
    ) {}

    /**
     * GET /api/v1/suggestions
     *
     * Viewer-scoped by SuggestionReader, so a profile with no suggestions — an
     * attendee, or a business the batch has not reached — gets an empty page
     * rather than a refusal.
     */
    public function index(ListSuggestionsRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $paginator = $this->suggestionReader->liveFor($profile, $request->perPage());

        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'data' => SuggestionResource::collection($paginator->getCollection())->resolve($request),
                'meta' => $meta,
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * GET /api/v1/suggestions/{suggestion}
     *
     * Ownership first, liveness second: an intruder must learn nothing from the
     * difference between "expired" and "not yours", so the 403 is decided before
     * the row's state is looked at.
     */
    public function show(Request $request, KolabSuggestion $suggestion): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $suggestion)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this suggestion.'),
            ], 403);
        }

        if (! $this->suggestionReader->isLive($suggestion)) {
            return response()->json([
                'success' => false,
                'message' => __('Resource not found'),
                'errors' => [
                    'resource' => [__('The requested resource was not found')],
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new SuggestionResource($this->suggestionReader->markClicked($suggestion)),
        ]);
    }

    /**
     * POST /api/v1/suggestions/{suggestion}/dismiss
     *
     * No liveness check here, unlike show(): a client holding a stale page must
     * be able to dismiss without an error, and the write feeds the cooldown. See
     * SuggestionReader::dismiss().
     */
    public function dismiss(Request $request, KolabSuggestion $suggestion): Response|JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('dismiss', $suggestion)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to dismiss this suggestion.'),
            ], 403);
        }

        $this->suggestionReader->dismiss($suggestion);

        return response()->noContent();
    }
}
