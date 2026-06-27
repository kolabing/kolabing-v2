<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KolabResource;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\KolabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedKolabController extends Controller
{
    public function __construct(
        private readonly KolabService $kolabService,
    ) {}

    /**
     * Save (bookmark) a kolab for the authenticated profile. Idempotent.
     *
     * POST /api/v1/kolabs/{kolab}/save
     */
    public function store(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to save this kolab.'),
            ], 403);
        }

        $this->kolabService->save($profile, $kolab);

        $kolab->load('creatorProfile');
        $kolab->loadExists(['savedByProfiles as is_saved' => function ($q) use ($profile): void {
            $q->whereKey($profile->id);
        }]);

        return response()->json([
            'success' => true,
            'message' => __('Kolab saved.'),
            'data' => new KolabResource($kolab),
        ]);
    }

    /**
     * Remove a kolab from the authenticated profile's saved list. Idempotent.
     *
     * DELETE /api/v1/kolabs/{kolab}/save
     */
    public function destroy(Request $request, Kolab $kolab): Response
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->kolabService->unsave($profile, $kolab);

        return response()->noContent();
    }
}
