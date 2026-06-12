<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    /**
     * Convert cart to order
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address' => 'nullable|array',
            'shipping_address.name' => 'nullable|string|max:255',
            'shipping_address.phone' => 'nullable|string|max:50',
            'shipping_address.address' => 'nullable|string|max:500',
            'shipping_address.city' => 'nullable|string|max:100',
            'shipping_address.country' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);

        if (empty($cart)) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        DB::beginTransaction();
        try {
            $orders = [];

            foreach ($cart as $item) {
                $product = Product::find($item['product_id']);
                if (!$product || $product->status !== 'active') {
                    continue;
                }

                $total = $product->price * $item['quantity'];

                $order = Order::create([
                    'buyer_profile_id' => $profileId,
                    'seller_profile_id' => $product->profile_id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total' => $total,
                    'currency' => $product->currency,
                    'status' => 'pending',
                    'payment_method' => 'stripe',
                    'shipping_address' => $validated['shipping_address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $orders[] = $order;
            }

            // Clear cart
            $request->session()->forget("commerce_cart.{$profileId}");

            DB::commit();

            return response()->json([
                'message' => 'Orders created successfully',
                'orders' => array_map(fn($o) => [
                    'id' => $o->id,
                    'product_id' => $o->product_id,
                    'quantity' => $o->quantity,
                    'total' => (float) $o->total,
                    'currency' => $o->currency,
                    'status' => $o->status,
                ], $orders),
                'total_amount' => array_sum(array_column($orders, 'total')),
                'currency' => 'USD',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed', ['error' => $e->getMessage(), 'profile_id' => $profileId]);
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user's orders
     */
    public function orders(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $orders = Order::where('buyer_profile_id', $profileId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * Get single order
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $order = Order::with('product')->findOrFail($id);

        if ($order->buyer_profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($order);
    }
}
