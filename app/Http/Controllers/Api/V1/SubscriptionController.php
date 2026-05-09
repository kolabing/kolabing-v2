<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Profile;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can have subscriptions'),
            ], 403);
        }

        $subscription = $this->subscriptionService->getSubscription($profile);

        if (! $subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('No subscription found'),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }
}
