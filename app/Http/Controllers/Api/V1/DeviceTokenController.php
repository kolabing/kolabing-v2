<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeviceTokenRequest;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    /**
     * Store legacy mobile token metadata for the authenticated user.
     * OneSignal delivery uses external_id targeting, but this stays for
     * backward compatibility with older mobile builds.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $validated = $request->validated();

        $profile->update([
            'device_token' => $validated['token'],
            'device_platform' => $validated['platform'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Device token registered successfully'),
        ]);
    }
}
