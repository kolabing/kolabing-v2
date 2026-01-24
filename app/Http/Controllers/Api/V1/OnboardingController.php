<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    /**
     * Complete business user onboarding.
     *
     * PUT /api/v1/onboarding/business
     */
    public function business(BusinessOnboardingRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $profile = $this->onboardingService->completeBusinessOnboarding(
            $profile,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => __('Business profile updated successfully'),
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Complete community user onboarding.
     *
     * PUT /api/v1/onboarding/community
     */
    public function community(CommunityOnboardingRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $profile = $this->onboardingService->completeCommunityOnboarding(
            $profile,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => __('Community profile updated successfully'),
            'data' => new UserResource($profile),
        ]);
    }
}
