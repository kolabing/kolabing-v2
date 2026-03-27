<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RestoreApplePurchasesRequest;
use App\Http\Requests\Api\V1\VerifyAppleTransactionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Profile;
use App\Services\AppleIAPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AppleIAPController extends Controller
{
    public function __construct(
        private readonly AppleIAPService $appleIAPService
    ) {}

    /**
     * Verify an Apple IAP transaction and create/update subscription.
     *
     * POST /api/v1/me/subscription/apple-verify
     */
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

        $transactionId = $request->input('transaction_id');

        if ($this->appleIAPService->transactionAlreadyRecorded($transactionId)) {
            $subscription = $profile->subscription;

            return response()->json([
                'success' => true,
                'data' => new SubscriptionResource($subscription),
                'message' => __('Transaction already verified.'),
            ], 409);
        }

        try {
            $transactionData = $this->appleIAPService->verifyTransaction($transactionId);
        } catch (\RuntimeException $e) {
            Log::warning('Apple IAP verify failed', ['error' => $e->getMessage(), 'transaction_id' => $transactionId]);

            return response()->json([
                'success' => false,
                'message' => __('Invalid transaction. Could not verify with Apple.'),
                'error' => 'apple_verification_failed',
            ], 400);
        }

        $subscription = $this->appleIAPService->findOrCreateSubscription($profile, $transactionData);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Restore Apple IAP purchases.
     *
     * POST /api/v1/me/subscription/apple-restore
     */
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

        $transactions = $request->input('transactions');

        foreach ($transactions as $tx) {
            try {
                $transactionData = $this->appleIAPService->verifyTransaction($tx['transaction_id']);
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
}
