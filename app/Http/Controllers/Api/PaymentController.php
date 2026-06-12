<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api')->except(['handleWebhook']);
    }

    /**
     * Create a checkout session (PaymentIntent) for a product.
     *
     * POST /api/v1/commerce/payment/create-checkout-session
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $quantity = $validated['quantity'] ?? 1;

        $product = Product::findOrFail($validated['product_id']);

        if ($product->status !== 'active') {
            return response()->json(['message' => 'Product is not available'], 400);
        }

        $total = $product->price * $quantity;

        DB::beginTransaction();
        try {
            $order = Order::create([
                'buyer_profile_id' => $profileId,
                'seller_profile_id' => $product->profile_id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'total' => $total,
                'currency' => $product->currency,
                'status' => 'pending',
                'payment_method' => 'stripe',
            ]);

            $paymentIntent = app(PaymentService::class)->createPaymentIntent($order);

            if (! $paymentIntent) {
                DB::rollBack();

                return response()->json(['message' => 'Failed to create payment intent'], 500);
            }

            DB::commit();

            return response()->json([
                'session_id' => $paymentIntent['id'],
                'client_secret' => $paymentIntent['client_secret'],
                'order_id' => $order->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('createCheckoutSession failed', [
                'error' => $e->getMessage(),
                'product_id' => $validated['product_id'],
                'profile_id' => $profileId,
            ]);

            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle Stripe webhook events.
     *
     * POST /api/v1/commerce/payment/webhook
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('stripe-signature', '');

        $result = app(PaymentService::class)->handleWebhook($payload, $sigHeader);

        return response()->json($result, 200);
    }

    /**
     * Show successful order details.
     *
     * GET /api/v1/commerce/payment/success/{orderId}
     *
     * @param  Request  $request
     * @param  int  $orderId
     * @return JsonResponse
     */
    public function success(Request $request, int $orderId): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $order = Order::with('product')->findOrFail($orderId);

        if ($order->buyer_profile_id !== $profileId && ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'order' => $order,
            'status' => $order->status,
        ]);
    }

    /**
     * Show cancelled order details.
     *
     * GET /api/v1/commerce/payment/cancel/{orderId}
     *
     * @param  Request  $request
     * @param  int  $orderId
     * @return JsonResponse
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $order = Order::with('product')->findOrFail($orderId);

        if ($order->buyer_profile_id !== $profileId && ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'order' => $order,
            'status' => $order->status,
        ]);
    }

    /**
     * Refund an order. Only admins or the seller may refund.
     *
     * POST /api/v1/commerce/payment/refund/{orderId}
     *
     * @param  Request  $request
     * @param  int  $orderId
     * @return JsonResponse
     */
    public function refund(Request $request, int $orderId): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $order = Order::with('product')->findOrFail($orderId);

        $isAdmin = $request->user()->is_admin;
        $isSeller = $order->seller_profile_id === $profileId;

        if (! $isAdmin && ! $isSeller) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! in_array($order->status, ['paid', 'completed'])) {
            return response()->json(['message' => 'Order cannot be refunded in its current state'], 400);
        }

        $amount = $request->input('amount');

        if ($amount !== null) {
            $request->validate([
                'amount' => 'numeric|min:0.01|max:' . $order->total,
            ]);
        }

        $refunded = app(PaymentService::class)->processRefund(
            $order,
            $amount !== null ? (float) $amount : null
        );

        if (! $refunded) {
            return response()->json(['message' => 'Refund failed'], 500);
        }

        return response()->json([
            'message' => 'Refund processed successfully',
            'order' => $order->fresh()->load('product'),
        ]);
    }
}
