<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->stripeService->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->subscriptionService->activateFromStripeSession($event->data->object),
                'customer.subscription.updated', 'customer.subscription.deleted' => $this->subscriptionService->syncFromStripeSubscription($event->data->object),
                default => null,
            };
        } catch (\Throwable $e) {
            // Log and still return 200 so Stripe does not retry-storm on an internal
            // error; reconciliation happens out of band.
            Log::error('Stripe webhook processing error', [
                'event_type' => $event->type,
                'event_id' => $event->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
