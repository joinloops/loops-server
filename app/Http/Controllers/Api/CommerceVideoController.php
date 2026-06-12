<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductVideo;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CommerceVideoController extends Controller
{
    /**
     * Get all products linked to a video
     */
    public function getVideoProducts(Request $request, string $videoId): JsonResponse
    {
        $products = ProductVideo::where('video_id', $videoId)
            ->with('product')
            ->whereHas('product', function ($q) {
                $q->where('status', 'active');
            })
            ->orderBy('timestamp_start')
            ->get()
            ->map(function ($pv) {
                $product = $pv->product;

                return [
                    'id' => $pv->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_description' => $product->description,
                    'price' => $product->price,
                    'currency' => $product->currency,
                    'images' => $product->images,
                    'category' => $product->category,
                    'external_url' => $product->external_url,
                    'bounding_box' => $pv->bounding_box_x !== null ? [
                        'x' => (float) $pv->bounding_box_x,
                        'y' => (float) $pv->bounding_box_y,
                        'w' => (float) $pv->bounding_box_w,
                        'h' => (float) $pv->bounding_box_h,
                    ] : null,
                    'timestamp_start' => (float) $pv->timestamp_start,
                    'timestamp_end' => (float) $pv->timestamp_end,
                    'detection_method' => $pv->detection_method,
                    'confidence' => (float) $pv->confidence,
                    'created_at' => $pv->created_at,
                ];
            });

        return response()->json([
            'video_id' => $videoId,
            'products' => $products,
            'count' => $products->count(),
        ]);
    }
}
