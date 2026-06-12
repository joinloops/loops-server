<?php

namespace App\Http\Controllers\Api;

use App\Models\Affiliate;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api')->except(['trackClick']);
    }

    /**
     * Create a new affiliate link
     */
    public function createLink(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Generate a unique referral code
        $referralCode = Str::random(10);
        while (Affiliate::where('referral_code', $referralCode)->exists()) {
            $referralCode = Str::random(10);
        }

        $affiliate = Affiliate::create([
            'profile_id' => $profileId,
            'referral_code' => $referralCode,
            'product_id' => $product->id,
            'commission_rate' => $validated['commission_rate'] ?? 10.00,
            'commission_type' => 'percentage',
            'total_earned' => 0,
            'total_clicks' => 0,
            'total_conversions' => 0,
            'status' => 'active',
        ]);

        $affiliate->load('product');

        return response()->json($affiliate, 201);
    }

    /**
     * Get current user's affiliate links
     */
    public function myLinks(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $links = Affiliate::where('profile_id', $profileId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($link) {
                return [
                    'id' => $link->id,
                    'referral_code' => $link->referral_code,
                    'product' => $link->product ? [
                        'id' => $link->product->id,
                        'name' => $link->product->name,
                        'price' => (float) $link->product->price,
                    ] : null,
                    'commission_rate' => (float) $link->commission_rate,
                    'commission_type' => $link->commission_type,
                    'total_clicks' => (int) $link->total_clicks,
                    'total_conversions' => (int) $link->total_conversions,
                    'total_earned' => (float) $link->total_earned,
                    'status' => $link->status,
                    'created_at' => $link->created_at,
                    'referral_url' => url("/r/{$link->referral_code}"),
                ];
            });

        return response()->json($links);
    }

    /**
     * Track an affiliate link click (no auth required)
     */
    public function trackClick(Request $request, string $code): JsonResponse
    {
        $affiliate = Affiliate::where('referral_code', $code)
            ->where('status', 'active')
            ->firstOrFail();

        $affiliate->increment('total_clicks');

        if ($affiliate->product && $affiliate->product->external_url) {
            $redirectUrl = $affiliate->product->external_url;
        } else {
            $redirectUrl = $affiliate->product
                ? url("/products/{$affiliate->product->id}")
                : url('/');
        }

        return response()->json([
            'redirect_url' => $redirectUrl,
            'product_id' => $affiliate->product_id,
            'referral_code' => $affiliate->referral_code,
        ]);
    }

    /**
     * Affiliate dashboard summary
     */
    public function dashboard(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $links = Affiliate::where('profile_id', $profileId);

        $totalEarned = (clone $links)->sum('total_earned');
        $totalClicks = (clone $links)->sum('total_clicks');
        $totalConversions = (clone $links)->sum('total_conversions');
        $linksCount = (clone $links)->count();

        return response()->json([
            'total_earned' => (float) $totalEarned,
            'total_clicks' => (int) $totalClicks,
            'total_conversions' => (int) $totalConversions,
            'links_count' => (int) $linksCount,
        ]);
    }

    /**
     * List all affiliates (admin only)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $affiliates = Affiliate::with('profile', 'product')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($affiliates);
    }
}
