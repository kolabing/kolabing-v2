<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    /**
     * Get the authenticated user's notification preferences.
     *
     * GET /api/v1/me/notification-preferences
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $preferences = $this->profileService->getOrCreateNotificationPreferences($profile);

        return response()->json([
            'success' => true,
            'data' => new NotificationPreferenceResource($preferences),
        ]);
    }

    /**
     * Update the authenticated user's notification preferences.
     *
     * PUT /api/v1/me/notification-preferences
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $preferences = $this->profileService->updateNotificationPreferences(
            $profile,
            $request->getPreferencesData()
        );

        return response()->json([
            'success' => true,
            'message' => __('Notification preferences updated successfully'),
            'data' => new NotificationPreferenceResource($preferences),
        ]);
    }
}
