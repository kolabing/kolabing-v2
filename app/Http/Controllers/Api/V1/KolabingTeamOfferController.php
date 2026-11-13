<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KolabingTeamOfferResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\ChatThread;
use App\Models\KolabingTeamOffer;
use App\Models\Profile;
use App\Services\AppleAuthService;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
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
        private readonly AuthService $auth,
        private readonly GoogleAuthService $google,
        private readonly AppleAuthService $apple,
    ) {}

    /**
     * GET /api/v1/kolabing-team/offers/{offer}
     *
     * What a tapped offer link (`/o/{id}`) resolves to: the card and the thread
     * it sits in, so the app can open the thread on the card. Only the recipient.
     */
    public function show(Request $request, KolabingTeamOffer $offer): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($offer->recipient_profile_id !== $profile->id) {
            return response()->json(['success' => false, 'message' => 'This offer is not yours.'], 403);
        }

        $offer->setAttribute('thread_id', ChatThread::query()->where('team_profile_id', $profile->id)->value('id'));

        return response()->json([
            'success' => true,
            'data' => ['offer' => new KolabingTeamOfferResource($offer->load(['kolab', 'communityProfile']))],
        ]);
    }

    /**
     * GET /api/v1/kolabing-team/claim/{token}  (public, the token is the proof)
     *
     * What the claim confirmation screen shows before sign-in: the card, whose
     * page it is, and the line that the owner can delete the page afterwards.
     */
    public function claimPreview(string $token): JsonResponse
    {
        $preview = $this->team->previewClaim($token);

        if ($preview === null) {
            return response()->json(['success' => false, 'message' => 'This claim link is not valid.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => new KolabingTeamOfferResource($preview['offer']),
                'profile_name' => $preview['profile_name'],
                'profile_type' => $preview['profile_type'],
                'disclaimer' => $preview['disclaimer'],
                'expired' => $preview['expired'],
                'claimable' => $preview['claimable'],
            ],
        ]);
    }

    /**
     * POST /api/v1/kolabing-team/claim/{token}
     *
     * The owner signs in with Google or Apple and claims the profile in one
     * step: accepting the offer makes them the profile holder and creates the
     * Kolab (SERR-1344). Answers like a login (token pair + user).
     */
    public function claim(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:google,apple'],
            'id_token' => ['required_if:provider,google', 'nullable', 'string'],
            'identity_token' => ['required_if:provider,apple', 'nullable', 'string'],
        ]);

        $identity = $data['provider'] === 'google'
            ? $this->google->verifyIdToken((string) $data['id_token'])
            : $this->apple->verifyIdentityToken((string) $data['identity_token']);

        if (! $identity) {
            return response()->json(['success' => false, 'message' => __('Invalid sign-in token')], 400);
        }

        try {
            $result = $this->team->claim($token, [
                'google_id' => $identity['google_id'] ?? null,
                'apple_id' => $identity['apple_id'] ?? null,
                'email' => $identity['email'] ?? null,
                'avatar_url' => $identity['avatar_url'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            $status = in_array($e->getCode(), [404, 409, 410, 422], true) ? $e->getCode() : 422;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }

        $session = $this->auth->startSessionFor($result['profile']);

        return response()->json([
            'success' => true,
            'data' => [
                ...$session,
                'token_type' => 'Bearer',
                'user' => new UserResource($result['profile']),
                'offer' => new KolabingTeamOfferResource($result['offer']->load(['kolab', 'communityProfile'])),
                'kolab_id' => $result['kolab']?->id,
                'application_id' => $result['application']?->id,
            ],
        ]);
    }

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
