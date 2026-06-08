<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidReferralCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RestoreApplePurchasesRequest;
use App\Http\Requests\Api\V1\VerifyAppleTransactionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Profile;
use App\Services\AppleIAPService;
use App\Services\PostHog\PostHogService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppleIAPController extends Controller
{
    public function __construct(
        private readonly AppleIAPService $appleIAPService,
        private readonly ReferralService $referralService,
        private readonly PostHogService $postHog,
    ) {}

    public function verify(VerifyAppleTransactionRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        $transactionId = (string) $request->input('transaction_id');
        $existingSubscription = $this->appleIAPService->findSubscriptionByTransactionId($transactionId);

        if ($existingSubscription !== null) {
            if ($existingSubscription->profile_id !== $profile->id) {
                return $this->invalidAppleVerificationResponse($transactionId);
            }

            return response()->json([
                'success' => true,
                'data' => new SubscriptionResource($existingSubscription),
            ]);
        }

        try {
            $transactionData = $this->appleIAPService->verifyTransaction($transactionId);
            $this->appleIAPService->assertTransactionMatchesRequest(
                $transactionData,
                (string) $request->input('original_transaction_id'),
                (string) $request->input('product_id'),
            );
        } catch (\RuntimeException $e) {
            Log::warning('Apple IAP verify failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return $this->invalidAppleVerificationResponse($transactionId);
        }

        try {
            $referralCode = $request->referralCode() !== null
                ? $this->referralService->validateCodeForProfile($profile, $request->referralCode())
                : null;
        } catch (InvalidReferralCodeException) {
            return $this->invalidReferralResponse();
        }

        try {
            $subscription = DB::transaction(function () use ($profile, $transactionData, $referralCode) {
                $subscription = $this->appleIAPService->findOrCreateSubscription($profile, $transactionData);

                if ($referralCode !== null) {
                    $this->referralService->rewardFirstPaidSubscription($profile, $subscription, $referralCode);
                }

                return $subscription;
            });
        } catch (\RuntimeException $e) {
            Log::warning('Apple IAP subscription sync failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'profile_id' => $profile->id,
            ]);

            return $this->invalidAppleVerificationResponse($transactionId);
        }

        $this->postHog->capture($profile, 'subscription_verified', [
            'source' => 'apple_iap',
            'product_id' => (string) $request->input('product_id'),
        ]);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    public function restore(RestoreApplePurchasesRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        foreach ($request->input('transactions') as $transaction) {
            try {
                $transactionData = $this->appleIAPService->verifyTransaction($transaction['transaction_id']);
                $this->appleIAPService->assertTransactionMatchesRequest(
                    $transactionData,
                    $transaction['original_transaction_id'],
                    $transaction['product_id'],
                );

                $subscription = $this->appleIAPService->findOrCreateSubscription($profile, $transactionData);

                return response()->json([
                    'success' => true,
                    'data' => new SubscriptionResource($subscription),
                    'message' => __('Subscription restored successfully.'),
                ]);
            } catch (\RuntimeException) {
                continue;
            }
        }

        return response()->json([
            'success' => false,
            'message' => __('No active subscription found for this Apple account.'),
            'is_active' => false,
        ], 404);
    }

    private function invalidAppleVerificationResponse(string $transactionId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Invalid transaction. Could not verify with Apple.'),
            'error' => 'apple_verification_failed',
            'transaction_id' => $transactionId,
        ], 400);
    }

    private function invalidReferralResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'referral_code' => ['The selected referral code is invalid.'],
            ],
        ], 422);
    }
}
