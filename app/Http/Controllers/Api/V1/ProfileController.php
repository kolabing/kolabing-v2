<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    /**
     * Get the authenticated user's full profile with subscription.
     *
     * GET /api/v1/me/profile
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $profile = $this->profileService->getFullProfile($profile);

        return response()->json([
            'success' => true,
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * PUT /api/v1/me/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        // Get profile data based on user type
        $profileData = $request->getProfileData();

        $extendedProfileData = $profile->isBusiness()
            ? $request->getBusinessProfileData()
            : $request->getCommunityProfileData();

        $profile = $this->profileService->updateProfile(
            $profile,
            $profileData,
            $extendedProfileData
        );

        return response()->json([
            'success' => true,
            'message' => __('Profile updated successfully'),
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Soft delete the authenticated user's account.
     *
     * DELETE /api/v1/me/account
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->profileService->deleteProfile($profile);

        return response()->json([
            'success' => true,
            'message' => __('Account deleted successfully'),
        ]);
    }
}
