<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteDeviceTokenRequest;
use App\Http\Requests\Api\V1\RegisterDeviceTokenRequest;
use App\Models\Profile;
use App\Services\Notifications\DeviceTokenService;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly DeviceTokenService $deviceTokenService
    ) {}

    /**
     * Register or update the authenticated user's FCM device token.
     *
     * POST /api/v1/me/device-token
     */
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->deviceTokenService->register($profile, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Device token registered successfully'),
        ]);
    }

    /**
     * DELETE /api/v1/me/device-token
     */
    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->deviceTokenService->unregister($profile, $request->validated('token'));

        return response()->json([
            'success' => true,
            'message' => __('Device token removed successfully'),
        ]);
    }
}
