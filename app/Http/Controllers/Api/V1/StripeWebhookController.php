<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Handle incoming Stripe webhook events.
     *
     * POST /api/v1/webhooks/stripe
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! $sigHeader || ! $webhookSecret) {
            return response()->json(['error' => 'Missing signature or webhook secret'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook parsing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
            default => Log::info('Unhandled Stripe event', ['type' => $event->type]),
        };

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(object $sessionData): void
    {
        $session = CheckoutSession::retrieve([
            'id' => $sessionData->id,
            'expand' => ['subscription'],
        ]);

        $this->subscriptionService->handleCheckoutCompleted($session);
    }

    private function handleSubscriptionUpdated(object $subscriptionData): void
    {
        $subscription = Subscription::retrieve($subscriptionData->id);

        $this->subscriptionService->handleSubscriptionUpdated($subscription);
    }

    private function handleSubscriptionDeleted(object $subscriptionData): void
    {
        $subscription = Subscription::retrieve($subscriptionData->id);

        $this->subscriptionService->handleSubscriptionDeleted($subscription);
    }

    private function handlePaymentFailed(object $invoiceData): void
    {
        $subscriptionId = $invoiceData->subscription ?? null;

        if ($subscriptionId) {
            $this->subscriptionService->handlePaymentFailed($subscriptionId);
        }
    }

    private function handlePaymentSucceeded(object $invoiceData): void
    {
        $subscriptionId = $invoiceData->subscription ?? null;

        if ($subscriptionId) {
            $this->subscriptionService->handlePaymentSucceeded($subscriptionId);
        }
    }
}
