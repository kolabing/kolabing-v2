<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidReferralCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BillingPortalRequest;
use App\Http\Requests\Api\V1\CreateCheckoutSessionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Profile;
use App\Services\ReferralService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly StripeService $stripeService,
        private readonly ReferralService $referralService,
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

    /**
     * Create a Stripe Checkout Session for a business subscription (web / Android
     * sales-driven path that bypasses Apple's IAP fee). The subscription is
     * activated asynchronously by the Stripe webhook on `checkout.session.completed`.
     */
    public function createCheckoutSession(CreateCheckoutSessionRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        if ($request->referralCode() !== null) {
            try {
                $this->referralService->validateCodeForProfile($profile, $request->referralCode());
            } catch (InvalidReferralCodeException) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid referral code'),
                    'errors' => ['referral_code' => [__('This referral code is not valid.')]],
                ], 422);
            }
        }

        try {
            $checkoutUrl = $this->stripeService->createCheckoutSession(
                $profile,
                $request->plan(),
                (string) $request->input('success_url'),
                (string) $request->input('cancel_url'),
                $request->referralCode(),
            );
        } catch (ApiErrorException|\RuntimeException $e) {
            Log::error('Stripe checkout session creation failed', [
                'profile_id' => $profile->id,
                'plan' => $request->plan(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Could not start checkout. Please try again.'),
            ], 502);
        }

        return response()->json([
            'data' => ['checkout_url' => $checkoutUrl],
        ]);
    }

    /**
     * Create a Stripe Billing Portal session so a business can manage or cancel
     * its Stripe subscription on the web. Apple-IAP / maintainer subscriptions are
     * not managed here (they carry no Stripe customer) — those return 409.
     */
    public function billingPortal(BillingPortalRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can manage subscriptions'),
            ], 403);
        }

        $subscription = $this->subscriptionService->getSubscription($profile);

        if ($subscription === null || blank($subscription->stripe_customer_id)) {
            return response()->json([
                'success' => false,
                'message' => __('No Stripe subscription to manage.'),
            ], 409);
        }

        try {
            $portalUrl = $this->stripeService->createBillingPortalSession(
                (string) $subscription->stripe_customer_id,
                (string) $request->input('return_url'),
            );
        } catch (ApiErrorException $e) {
            Log::error('Stripe billing portal session creation failed', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Could not open the billing portal. Please try again.'),
            ], 502);
        }

        return response()->json([
            'data' => ['portal_url' => $portalUrl],
        ]);
    }
}
