<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    /**
     * Register as a vendor
     */
    public function register(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $existing = Vendor::where('profile_id', $profileId)->first();
        if ($existing) {
            return response()->json(['message' => 'You are already registered as a vendor.'], 409);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $validated['profile_id'] = $profileId;
        $validated['status'] = 'pending';

        $vendor = Vendor::create($validated);

        return response()->json($vendor, 201);
    }

    /**
     * Get current user's vendor profile with stats
     */
    public function show(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $vendor = Vendor::with('profile')
            ->where('profile_id', $profileId)
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 404);
        }

        return response()->json($vendor);
    }

    /**
     * Update vendor profile
     */
    public function update(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $vendor = Vendor::where('profile_id', $profileId)->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 404);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'business_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'payout_method' => 'nullable|string|max:50',
            'payout_details' => 'nullable|array',
        ]);

        $vendor->update($validated);

        return response()->json($vendor);
    }

    /**
     * Dashboard stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $vendor = Vendor::where('profile_id', $profileId)->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 404);
        }

        $totalProducts = Product::where('profile_id', $profileId)->count();
        $totalOrders = Order::whereHas('items.product', function ($q) use ($profileId) {
            $q->where('profile_id', $profileId);
        })->count();
        $totalSales = (float) $vendor->total_sales;
        $balance = (float) $vendor->balance;

        return response()->json([
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_sales' => $totalSales,
            'balance' => $balance,
        ]);
    }

    /**
     * Get vendor's products paginated
     */
    public function products(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $vendor = Vendor::where('profile_id', $profileId)->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 404);
        }

        $products = Product::where('profile_id', $profileId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($products);
    }

    /**
     * Get orders for vendor's products paginated
     */
    public function orders(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $vendor = Vendor::where('profile_id', $profileId)->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found.'], 404);
        }

        $orders = Order::whereHas('items.product', function ($q) use ($profileId) {
            $q->where('profile_id', $profileId);
        })->with(['items.product', 'buyer'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($orders);
    }

    /**
     * List all vendors (admin only)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $vendors = Vendor::with('profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($vendors);
    }

    /**
     * Approve or reject a vendor (admin only)
     */
    public function adminVerify(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:active,rejected',
        ]);

        $data = ['status' => $validated['status']];

        if ($validated['status'] === 'active') {
            $data['verified_at'] = now();
        }

        $vendor->update($data);

        return response()->json($vendor);
    }
}
