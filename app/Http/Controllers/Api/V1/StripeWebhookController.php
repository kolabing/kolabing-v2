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
            Log::error('Stripe webhook processing error', [
                'event_type' => $event->type,
                'event_id' => $event->id ?? null,
                'error' => $e->getMessage(),
            ]);

            // Fail loudly so Stripe redelivers. Swallowing this with a 200 marks the
            // event delivered forever: a transient Stripe/DB blip would leave a
            // customer charged with no `business_subscriptions` row and no way back
            // in — and there is no reconciliation job. Both handlers key off
            // `profile_id` via updateOrCreate, so a redelivery is idempotent.
            // Stripe backs its retries off over ~3 days; this is not a retry storm.
            return response()->json(['success' => false, 'message' => 'Processing error'], 500);
        }

        return response()->json(['success' => true]);
    }
}
