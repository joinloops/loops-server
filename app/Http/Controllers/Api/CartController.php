<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    /**
     * Get current user's cart
     */
    public function index(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);

        // Enrich with product details
        $items = [];
        $total = 0;
        foreach ($cart as $item) {
            $product = Product::find($item['product_id']);
            if (!$product || $product->status !== 'active') {
                continue;
            }
            $subtotal = $product->price * $item['quantity'];
            $total += $subtotal;
            $items[] = [
                'cart_id' => $item['cart_id'],
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => (float) $product->price,
                'currency' => $product->currency,
                'image' => $product->images[0] ?? null,
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal,
                'stock' => $product->stock,
            ];
        }

        return response()->json([
            'items' => $items,
            'total' => $total,
            'count' => count($items),
            'currency' => 'USD',
        ]);
    }

    /**
     * Add item to cart
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if ($product->status !== 'active') {
            return response()->json(['message' => 'Product is not available'], 400);
        }

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);

        // Check if product already in cart
        foreach ($cart as &$item) {
            if ($item['product_id'] === (int) $validated['product_id']) {
                $item['quantity'] += $validated['quantity'];
                $request->session()->put("commerce_cart.{$profileId}", $cart);
                return response()->json(['message' => 'Cart updated', 'cart_id' => $item['cart_id']]);
            }
        }

        // Add new item
        $cartItem = [
            'cart_id' => uniqid('cart_', true),
            'product_id' => (int) $validated['product_id'],
            'quantity' => $validated['quantity'],
            'added_at' => now()->toIso8601String(),
        ];
        $cart[] = $cartItem;
        $request->session()->put("commerce_cart.{$profileId}", $cart);

        return response()->json(['message' => 'Added to cart', 'cart_id' => $cartItem['cart_id']], 201);
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, string $cartId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);

        foreach ($cart as $i => &$item) {
            if ($item['cart_id'] === $cartId) {
                if ($validated['quantity'] <= 0) {
                    array_splice($cart, $i, 1);
                    $request->session()->put("commerce_cart.{$profileId}", $cart);
                    return response()->json(['message' => 'Item removed from cart']);
                }
                $item['quantity'] = $validated['quantity'];
                $request->session()->put("commerce_cart.{$profileId}", $cart);
                return response()->json(['message' => 'Cart updated']);
            }
        }

        return response()->json(['message' => 'Item not found in cart'], 404);
    }

    /**
     * Remove item from cart
     */
    public function remove(Request $request, string $cartId): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);

        foreach ($cart as $i => $item) {
            if ($item['cart_id'] === $cartId) {
                array_splice($cart, $i, 1);
                $request->session()->put("commerce_cart.{$profileId}", $cart);
                return response()->json(['message' => 'Item removed from cart']);
            }
        }

        return response()->json(['message' => 'Item not found in cart'], 404);
    }

    /**
     * Clear cart
     */
    public function clear(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $request->session()->forget("commerce_cart.{$profileId}");

        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * Get cart count (for badge)
     */
    public function count(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $cart = $request->session()->get("commerce_cart.{$profileId}", []);
        $count = array_sum(array_column($cart, 'quantity'));

        return response()->json(['count' => $count]);
    }
}
