<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CommerceAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    /**
     * Get commerce overview analytics
     */
    public function overview(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        // Total revenue from completed orders
        $totalRevenue = Order::where('status', 'completed')
            ->sum('total');

        // Total orders
        $totalOrders = Order::count();

        // Total products
        $totalProducts = Product::count();

        // Average order value
        $avgOrderValue = $totalOrders > 0
            ? round($totalRevenue / $totalOrders, 2)
            : 0;

        // Conversion rate: orders / (some base, e.g. total products with orders)
        $productsWithOrders = Product::whereHas('orders')->count();
        $conversionRate = $totalProducts > 0
            ? round(($productsWithOrders / $totalProducts) * 100, 2)
            : 0;

        // Vendor stats
        $totalVendors = DB::table('vendors')->count();
        $newVendors = DB::table('vendors')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => (int) $totalOrders,
            'total_products' => (int) $totalProducts,
            'conversion_rate' => (float) $conversionRate,
            'avg_order_value' => (float) $avgOrderValue,
            'new_vendors' => (int) $newVendors,
            'total_vendors' => (int) $totalVendors,
        ]);
    }

    /**
     * Get last 30 days revenue grouped by date
     */
    public function revenue(Request $request): JsonResponse
    {
        $revenue = Order::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($revenue);
    }

    /**
     * Get top 10 selling products
     */
    public function products(Request $request): JsonResponse
    {
        $topProducts = Product::select('products.*')
            ->selectRaw('COUNT(orders.id) as order_count')
            ->selectRaw('SUM(orders.total) as total_revenue')
            ->leftJoin('orders', 'products.id', '=', 'orders.product_id')
            ->where('orders.status', 'completed')
            ->groupBy('products.id')
            ->orderByDesc('order_count')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'order_count' => (int) $product->order_count,
                    'total_revenue' => (float) ($product->total_revenue ?? 0),
                ];
            });

        return response()->json($topProducts);
    }

    /**
     * Get vendor performance stats
     */
    public function vendorPerformance(Request $request): JsonResponse
    {
        $vendors = DB::table('vendors')
            ->leftJoin('orders', function ($join) {
                $join->on('vendors.profile_id', '=', 'orders.seller_profile_id')
                    ->where('orders.status', '=', 'completed');
            })
            ->select(
                'vendors.id',
                'vendors.profile_id',
                'vendors.business_name',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('COALESCE(SUM(orders.total), 0) as total_revenue')
            )
            ->groupBy('vendors.id', 'vendors.profile_id', 'vendors.business_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'profile_id' => $vendor->profile_id,
                    'business_name' => $vendor->business_name,
                    'order_count' => (int) $vendor->order_count,
                    'total_revenue' => (float) $vendor->total_revenue,
                ];
            });

        return response()->json($vendors);
    }

    /**
     * Get AI detection accuracy stats
     */
    public function aiAccuracy(Request $request): JsonResponse
    {
        $totalDetections = DB::table('videos')
            ->whereNotNull('ai_detected')
            ->count();

        $aiTagged = DB::table('videos')
            ->where('ai_detected', true)
            ->count();

        $accuracyRate = $totalDetections > 0
            ? round(($aiTagged / $totalDetections) * 100, 2)
            : 0;

        return response()->json([
            'total_analyzed' => $totalDetections,
            'ai_tagged' => $aiTagged,
            'accuracy_rate' => $accuracyRate,
        ]);
    }
}
