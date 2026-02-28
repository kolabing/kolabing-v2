<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateCheckoutSessionRequest;
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

    /**
     * Get the authenticated user's subscription details.
     *
     * GET /api/v1/me/subscription
     */
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

    /**
     * Create a Stripe checkout session for subscription.
     *
     * POST /api/v1/me/subscription/checkout
     */
    public function checkout(CreateCheckoutSessionRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        // Check if user already has an active subscription
        if ($profile->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => __('You already have an active subscription'),
            ], 400);
        }

        try {
            $result = $this->subscriptionService->createCheckoutSession(
                $profile,
                $request->getSuccessUrl(),
                $request->getCancelUrl()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get the Stripe billing portal URL.
     *
     * GET /api/v1/me/subscription/portal
     */
    public function portal(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can access billing portal'),
            ], 403);
        }

        $returnUrl = $request->query('return_url', config('app.url'));

        try {
            $result = $this->subscriptionService->getBillingPortalUrl(
                $profile,
                (string) $returnUrl
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Cancel the subscription at period end.
     *
     * POST /api/v1/me/subscription/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can cancel subscriptions'),
            ], 403);
        }

        try {
            $subscription = $this->subscriptionService->cancelSubscription($profile);

            return response()->json([
                'success' => true,
                'message' => __('Subscription will be cancelled at the end of the billing period'),
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Reactivate a subscription that is scheduled for cancellation.
     *
     * POST /api/v1/me/subscription/reactivate
     */
    public function reactivate(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can reactivate subscriptions'),
            ], 403);
        }

        try {
            $subscription = $this->subscriptionService->reactivateSubscription($profile);

            return response()->json([
                'success' => true,
                'message' => __('Subscription has been reactivated'),
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
