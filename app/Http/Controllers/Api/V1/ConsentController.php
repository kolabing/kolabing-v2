<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    /**
     * Record the authenticated user's acceptance of the current Terms of
     * Service + Privacy Policy version. Used by the app to clear the
     * re-consent gate when the published terms change.
     *
     * POST /api/v1/me/consent
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $version = (string) config('legal.terms_version');

        $profile->update([
            'terms_accepted_at' => now(),
            'terms_version' => $version,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Consent recorded'),
            'data' => [
                'terms' => [
                    'current_version' => $version,
                    'accepted_version' => $profile->terms_version,
                    'accepted_at' => $profile->terms_accepted_at?->toIso8601String(),
                    'needs_acceptance' => $profile->needsTermsAcceptance(),
                ],
            ],
        ]);
    }
}
