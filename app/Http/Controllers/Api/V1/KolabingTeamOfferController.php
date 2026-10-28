<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KolabingTeamOfferResource;
use App\Models\KolabingTeamOffer;
use App\Models\Profile;
use App\Services\KolabingTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The recipient's answer to an offer card in the "Kolabing team" thread.
 * Offers are created and sent from admin only; this is the only API surface.
 */
class KolabingTeamOfferController extends Controller
{
    public function __construct(
        private readonly KolabingTeamService $team,
    ) {}

    /**
     * POST /api/v1/kolabing-team/offers/{offer}/accept
     */
    public function accept(Request $request, KolabingTeamOffer $offer): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $result = $this->team->accept($offer, $profile);
        } catch (InvalidArgumentException $e) {
            return $this->refuse($offer, $profile, $e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => new KolabingTeamOfferResource($result['offer']->load(['kolab', 'communityProfile'])),
                'kolab_id' => $result['kolab']?->id,
                'application_id' => $result['application']?->id,
            ],
        ]);
    }

    /**
     * POST /api/v1/kolabing-team/offers/{offer}/decline
     */
    public function decline(Request $request, KolabingTeamOffer $offer): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $declined = $this->team->decline($offer, $profile);
        } catch (InvalidArgumentException $e) {
            return $this->refuse($offer, $profile, $e);
        }

        return response()->json([
            'success' => true,
            'data' => ['offer' => new KolabingTeamOfferResource($declined->load(['kolab', 'communityProfile']))],
        ]);
    }

    private function refuse(KolabingTeamOffer $offer, Profile $profile, InvalidArgumentException $e): JsonResponse
    {
        // Someone else's offer is a 403; an answered or no-longer-valid one is a 422.
        $status = $offer->recipient_profile_id !== $profile->id ? 403 : 422;

        return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
    }
}
