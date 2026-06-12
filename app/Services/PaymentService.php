<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;

class PaymentService
{
    protected StripeClient $stripe;

    protected string $webhookSecret;

    public function __construct()
    {
        $this->webhookSecret = config('services.stripe.webhook_secret', '');

        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setApiVersion('2024-09-30.acacia');

        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe PaymentIntent for the given order.
     *
     * @return array{id: string, client_secret: string}|null
     */
    public function createPaymentIntent(Order $order): ?array
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount' => $this->toCents($order->total),
                'currency' => strtolower($order->currency),
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'buyer_profile_id' => (string) $order->buyer_profile_id,
                    'seller_profile_id' => (string) ($order->seller_profile_id ?? ''),
                    'product_id' => (string) $order->product_id,
                ],
                'description' => 'Order #' . $order->id,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            $order->update([
                'payment_id' => $intent->id,
                'payment_method' => 'stripe',
            ]);

            return [
                'id' => $intent->id,
                'client_secret' => $intent->client_secret,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe createPaymentIntent failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Confirm a Stripe PaymentIntent for the given order.
     *
     * @return bool True if the payment was successfully confirmed (requires_capture or succeeded).
     */
    public function confirmPayment(Order $order, string $paymentMethodId): bool
    {
        try {
            $paymentIntentId = $order->payment_id;

            if (! $paymentIntentId) {
                Log::warning('confirmPayment called without payment_id', ['order_id' => $order->id]);

                return false;
            }

            $intent = $this->stripe->paymentIntents->confirm($paymentIntentId, [
                'payment_method' => $paymentMethodId,
            ]);

            if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                $order->update(['status' => 'paid']);

                return true;
            }

            if ($intent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
                $order->update(['status' => 'paid']);

                return true;
            }

            // Payment_intent requires further action (e.g., 3DS)
            Log::info('Stripe payment requires action', [
                'order_id' => $order->id,
                'status' => $intent->status,
                'next_action' => $intent->next_action,
            ]);

            return false;
        } catch (ApiErrorException $e) {
            Log::error('Stripe confirmPayment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle a Stripe webhook event.
     *
     * @return array{type: string, handled: bool, message?: string}
     */
    public function handleWebhook(string $payload, string $sigHeader): array
    {
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: invalid payload', ['error' => $e->getMessage()]);

            return ['type' => 'invalid_payload', 'handled' => false, 'message' => 'Invalid payload'];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);

            return ['type' => 'invalid_signature', 'handled' => false, 'message' => 'Invalid signature'];
        }

        $handled = match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            'charge.dispute.created' => $this->handleDisputeCreated($event->data->object),
            default => false,
        };

        return [
            'type' => $event->type,
            'handled' => $handled,
        ];
    }

    /**
     * Process a full or partial refund for an order.
     */
    public function processRefund(Order $order, ?float $amount = null): bool
    {
        try {
            $paymentIntentId = $order->payment_id;

            if (! $paymentIntentId) {
                Log::warning('processRefund called without payment_id', ['order_id' => $order->id]);

                return false;
            }

            $params = [];

            if ($amount !== null && $amount < $order->total) {
                $params['amount'] = $this->toCents($amount);
            }

            $refund = $this->stripe->refunds->create([
                'payment_intent' => $paymentIntentId,
                ...$params,
            ]);

            $order->update(['status' => 'refunded']);

            Log::info('Stripe refund processed', [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'amount' => $amount ?? $order->total,
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retrieve a Stripe PaymentIntent by ID.
     */
    public function retrievePaymentIntent(string $id): ?PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->retrieve($id);
        } catch (ApiErrorException $e) {
            Log::error('Stripe retrievePaymentIntent failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cancel a Stripe PaymentIntent.
     */
    public function cancelPaymentIntent(Order $order): bool
    {
        try {
            $paymentIntentId = $order->payment_id;

            if (! $paymentIntentId) {
                return false;
            }

            $this->stripe->paymentIntents->cancel($paymentIntentId);

            $order->update(['status' => 'cancelled']);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancelPaymentIntent failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle payment_intent.succeeded webhook event.
     */
    protected function handlePaymentIntentSucceeded(PaymentIntent $intent): bool
    {
        $orderId = $intent->metadata['order_id'] ?? null;

        if (! $orderId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing order_id', [
                'intent_id' => $intent->id,
            ]);

            return false;
        }

        $order = Order::find($orderId);

        if (! $order) {
            Log::warning('Stripe webhook: order not found', [
                'order_id' => $orderId,
                'intent_id' => $intent->id,
            ]);

            return false;
        }

        $order->update([
            'status' => 'paid',
            'payment_id' => $intent->id,
        ]);

        Log::info('Stripe webhook: payment succeeded', [
            'order_id' => $order->id,
            'intent_id' => $intent->id,
        ]);

        return true;
    }

    /**
     * Handle payment_intent.payment_failed webhook event.
     */
    protected function handlePaymentIntentFailed(PaymentIntent $intent): bool
    {
        $orderId = $intent->metadata['order_id'] ?? null;

        if (! $orderId) {
            return false;
        }

        $order = Order::find($orderId);

        if (! $order) {
            return false;
        }

        $lastError = $intent->last_payment_error;

        $order->update([
            'status' => 'cancelled',
            'notes' => 'Payment failed: ' . ($lastError->message ?? 'Unknown error'),
        ]);

        Log::info('Stripe webhook: payment failed', [
            'order_id' => $order->id,
            'intent_id' => $intent->id,
            'error' => $lastError->message ?? 'unknown',
        ]);

        return true;
    }

    /**
     * Handle charge.refunded webhook event.
     */
    protected function handleChargeRefunded($charge): bool
    {
        $paymentIntentId = $charge->payment_intent;

        if (! $paymentIntentId) {
            return false;
        }

        $order = Order::where('payment_id', $paymentIntentId)->first();

        if (! $order) {
            return false;
        }

        $order->update(['status' => 'refunded']);

        Log::info('Stripe webhook: charge refunded', [
            'order_id' => $order->id,
            'charge_id' => $charge->id,
        ]);

        return true;
    }

    /**
     * Handle charge.dispute.created webhook event.
     */
    protected function handleDisputeCreated($dispute): bool
    {
        $paymentIntentId = $dispute->payment_intent;

        if (! $paymentIntentId) {
            return false;
        }

        $order = Order::where('payment_id', $paymentIntentId)->first();

        if (! $order) {
            return false;
        }

        Log::warning('Stripe webhook: dispute created', [
            'order_id' => $order->id,
            'dispute_id' => $dispute->id,
            'reason' => $dispute->reason,
        ]);

        return true;
    }

    /**
     * Convert a dollar amount to cents for Stripe.
     */
    protected function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert cents back to dollars.
     */
    protected function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
