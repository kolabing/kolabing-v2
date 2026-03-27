<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppleIAPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppleWebhookController extends Controller
{
    public function __construct(
        private readonly AppleIAPService $appleIAPService
    ) {}

    /**
     * Handle incoming Apple Server Notifications V2.
     *
     * POST /api/v1/webhooks/apple
     * No auth — Apple sends signed JWS payload.
     * Must return 200 within 5 seconds or Apple retries.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload');

        if (! $signedPayload) {
            return response()->json([], 200);
        }

        try {
            $notification = $this->appleIAPService->decodeSignedJwt($signedPayload);
        } catch (\Exception $e) {
            Log::warning('Apple webhook JWS decode failed', ['error' => $e->getMessage()]);

            return response()->json([], 200);
        }

        $notificationType = $notification['notificationType'] ?? null;
        $subtype = $notification['subtype'] ?? '';
        $signedTransactionInfo = $notification['data']['signedTransactionInfo'] ?? null;

        if (! $notificationType || ! $signedTransactionInfo) {
            return response()->json([], 200);
        }

        try {
            $transactionData = $this->appleIAPService->decodeSignedJwt($signedTransactionInfo);
            $this->appleIAPService->handleNotification($notificationType, $transactionData, $subtype);

            Log::info('Apple webhook processed', [
                'type' => $notificationType,
                'original_transaction_id' => $transactionData['originalTransactionId'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Apple webhook processing failed', [
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([], 200);
    }
}
